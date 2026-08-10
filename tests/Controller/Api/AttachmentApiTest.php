<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Attachment\AttachmentPolicy;
use App\Attachment\AttachmentStorage;
use App\Entity\Attachment;
use App\Enum\ApiTokenScope;
use App\Http\ProblemType;
use App\Repository\AttachmentRepository;
use App\Tests\ApiTestCase;
use Imagick;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;
use function strlen;

/**
 * The policy is the point of this endpoint. An attachment is an exception to the
 * rule that everything is described in text, so the tests are mostly about what
 * it refuses.
 */
final class AttachmentApiTest extends ApiTestCase
{
    private string $token;
    private string $ticketKey;

    /**
     * @var list<string>
     */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->createToken();
        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $this->token);
        $projectKey = $this->responsePayload()['key'];

        $this->request('POST', sprintf('/api/projects/%s/tickets', $projectKey), [
            'title' => 'Écran blanc au premier chargement',
            'type' => 'bug',
        ], $this->token);
        $this->ticketKey = $this->responsePayload()['key'];
    }

    protected function tearDown(): void
    {
        // The database rolls back; the bytes on disk do not.
        $storage = static::getContainer()->get(AttachmentStorage::class);

        foreach ($this->written as $path) {
            $storage->removePath($path);
        }

        parent::tearDown();
    }

    public function testAnImageIsAcceptedAndComesBackDescribed(): void
    {
        $this->attach($this->pngFile(2400, 1200), 'Capture of the blank screen, not reproducible since.');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->responsePayload();

        self::assertSame('image/webp', $payload['mimeType']);
        self::assertSame(64, strlen((string) $payload['checksum']));
        self::assertLessThanOrEqual(AttachmentPolicy::MAX_STORED_BYTES, $payload['size']);

        $this->rememberWritten();
    }

    public function testTheStoredFileIsNamedByTheServerAndLivesOutsideTheWebRoot(): void
    {
        $this->attach($this->pngFile(400, 300), 'Capture of a transient state.');
        $attachment = $this->rememberWritten();

        self::assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/[0-9a-f]{32}\.webp$#',
            $attachment->getStoragePath(),
            'The client name must not decide where the bytes land.',
        );

        $absolute = static::getContainer()->get(AttachmentStorage::class)->absolutePath($attachment->getStoragePath());

        self::assertFileExists($absolute);
        self::assertStringNotContainsString('/public/', $absolute);
    }

    public function testTheOriginalNameIsKeptForDisplayOnly(): void
    {
        $this->attach($this->pngFile(200, 200), 'Capture.', '../../etc/passwd.png');
        $attachment = $this->rememberWritten();

        self::assertStringNotContainsString('/', $attachment->getOriginalFilename());
        self::assertStringNotContainsString('..', $attachment->getStoragePath());
    }

    /**
     * The reason is what makes the exception reviewable six months later, so it
     * is recorded on the ticket rather than dropped.
     */
    public function testTheReasonIsRecordedAsAComment(): void
    {
        $this->attach($this->pngFile(200, 200), 'The page vanished on reload, nothing reproduces it.');
        $this->rememberWritten();

        $this->request('GET', '/api/tickets/'.$this->ticketKey, token: $this->token);

        $bodies = array_column($this->responsePayload()['comments'], 'body');

        self::assertCount(1, $bodies);
        self::assertStringContainsString('nothing reproduces it', $bodies[0]);
    }

    public function testAnAttachmentWithoutAReasonIsRefused(): void
    {
        $this->attach($this->pngFile(200, 200), '   ');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ProblemType::ATTACHMENT_REJECTED, $this->responsePayload()['type']);
    }

    public function testATypeOutsideTheWhitelistIsRefused(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $binary, "\x7fELF\x02\x01\x01".str_repeat("\x00", 200));

        $this->attach(
            new UploadedFile((string) $binary, 'tool.bin', 'text/plain', test: true),
            'Output that cannot be reproduced.',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ProblemType::ATTACHMENT_REJECTED, $this->responsePayload()['type']);
    }

    /**
     * The declared Content-Type is whatever the client felt like writing.
     */
    public function testTheDeclaredContentTypeIsNotTrusted(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $binary, "\x7fELF\x02\x01\x01".str_repeat("\x00", 200));

        $this->attach(
            new UploadedFile((string) $binary, 'screenshot.png', 'image/png', test: true),
            'Capture.',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testALogIsAcceptedAndStoredCompressed(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $log, str_repeat("2026-07-27 the same line over and over\n", 500));
        $original = (int) filesize((string) $log);

        $this->attach(new UploadedFile((string) $log, 'run.log', 'text/plain', test: true), 'Output that cannot be replayed.');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->responsePayload();

        self::assertSame('application/gzip', $payload['mimeType']);
        self::assertLessThan($original, $payload['size']);

        $this->rememberWritten();
    }

    public function testAttachingNeedsItsOwnScope(): void
    {
        $narrow = $this->createToken([ApiTokenScope::TicketsWrite]);

        $this->client->request(
            'POST',
            sprintf('/api/tickets/%s/attachments', $this->ticketKey),
            ['reason' => 'Capture.'],
            ['file' => $this->pngFile(200, 200)],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$narrow],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnUnknownTicketIsANotFound(): void
    {
        $this->client->request(
            'POST',
            '/api/tickets/NOPE-1/attachments',
            ['reason' => 'Capture.'],
            ['file' => $this->pngFile(200, 200)],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function attach(UploadedFile $file, string $reason, ?string $name = null): void
    {
        $this->client->request(
            'POST',
            sprintf('/api/tickets/%s/attachments', $this->ticketKey),
            ['reason' => $reason],
            ['file' => null === $name ? $file : new UploadedFile($file->getPathname(), $name, $file->getClientMimeType(), test: true)],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );
    }

    private function rememberWritten(): Attachment
    {
        $attachment = static::getContainer()->get(AttachmentRepository::class)->findOneBy([], ['id' => 'DESC']);

        self::assertInstanceOf(Attachment::class, $attachment);

        $this->written[] = $attachment->getStoragePath();

        return $attachment;
    }

    private function pngFile(int $width, int $height): UploadedFile
    {
        $image = new Imagick();
        $image->newPseudoImage($width, $height, 'plasma:fractal');
        $image->setImageFormat('png');

        $path = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $path, $image->getImagesBlob());

        return new UploadedFile((string) $path, 'capture.png', 'image/png', test: true);
    }
}
