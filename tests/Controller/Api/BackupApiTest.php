<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Attachment\AttachmentStorage;
use App\Attachment\AttachmentUploader;
use App\Domain\Backup\BackupService;
use App\Entity\Attachment;
use App\Repository\AttachmentRepository;
use App\Tests\InterfaceTestCase;
use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function gzdecode;
use function hash;
use function rawurlencode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * The backup API (database dump, attachments), behind the shared secret of .env.test and outside the token firewall.
 */
final class BackupApiTest extends InterfaceTestCase
{
    private const string SECRET = 'test-backup-secret';

    /**
     * @var list<string>
     */
    private array $written = [];

    protected function tearDown(): void
    {
        $storage = static::getContainer()->get(AttachmentStorage::class);

        foreach ($this->written as $path) {
            $storage->removePath($path);
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function routes(): iterable
    {
        yield 'dump' => ['/api/database/dump'];
        yield 'media files' => ['/api/media/files'];
        yield 'media download' => ['/api/media/download'];
    }

    #[DataProvider('routes')]
    public function testWithoutTheSecretTheApiAnswersNotFound(string $url): void
    {
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', $url.'?secret=wrong');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', $url, server: ['HTTP_X_BACKUP_SECRET' => 'wrong']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnEmptySecretKeepsTheApiClosed(): void
    {
        $container = static::getContainer();
        $service = new BackupService($container->get(AttachmentRepository::class), $container->get(AttachmentStorage::class), '');

        self::assertFalse($service->isAuthorized(''));
        self::assertFalse($service->isAuthorized(null));
    }

    /**
     * The secret opens the three backup paths and nothing else under /api, which still wants a token.
     */
    public function testTheSecretDoesNotOpenTheRestOfTheApi(): void
    {
        $this->client->request('GET', '/api/tickets', server: ['HTTP_X_BACKUP_SECRET' => self::SECRET]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheFileIndexListsTheAttachmentsAfterTheCursor(): void
    {
        $attachment = $this->attach();

        $files = $this->index(0);
        $ids = array_column($files, 'id');
        self::assertContains($attachment->getId(), $ids);
        self::assertSame($ids, array_values(array_unique($ids)));
        self::assertSame($ids, array_values(array_filter($ids, is_int(...))));

        $position = array_search($attachment->getId(), $ids, true);
        self::assertIsInt($position);
        $listed = $files[$position];
        self::assertSame($attachment->getStoragePath(), $listed['path']);
        self::assertTrue($listed['exists']);
        self::assertSame($attachment->getSize(), $listed['size']);
        self::assertSame($attachment->getChecksum(), $listed['hash']);

        self::assertSame([], $this->index(max($ids)));
    }

    public function testAnAttachmentMissingFromTheDiskIsListedAsNotExisting(): void
    {
        $attachment = $this->attach();
        static::getContainer()->get(AttachmentStorage::class)->removePath($attachment->getStoragePath());

        $files = $this->index($attachment->getId() - 1);

        self::assertFalse($files[0]['exists']);
    }

    public function testAnAttachmentIsDownloadedByItsListedPath(): void
    {
        $attachment = $this->attach();
        $listed = $this->index($attachment->getId() - 1)[0];

        $this->client->request('GET', '/api/media/download?path='.rawurlencode($listed['path']), server: ['HTTP_X_BACKUP_SECRET' => self::SECRET]);

        self::assertResponseIsSuccessful();
        $body = $this->client->getInternalResponse()->getContent();
        self::assertSame($listed['hash'], hash('sha256', $body));
        self::assertSame($listed['size'], strlen($body));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unreachablePaths(): iterable
    {
        yield 'parent directory' => ['../../.env'];
        yield 'inside the directory' => ['2026/10/../../../.env'];
        yield 'another directory of the checkout' => ['composer.json'];
        yield 'well formed but unknown' => ['2026/10/00000000000000000000000000000000.webp'];
        yield 'no path' => [''];
    }

    #[DataProvider('unreachablePaths')]
    public function testOnlyThePathOfAKnownAttachmentCanBeDownloaded(string $path): void
    {
        $this->client->request('GET', '/api/media/download?path='.rawurlencode($path), server: ['HTTP_X_BACKUP_SECRET' => self::SECRET]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheDumpIsAGzippedCompleteSqlDump(): void
    {
        $this->client->request('GET', '/api/database/dump?compress=1&secret='.self::SECRET);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/gzip');

        $sql = gzdecode((string) $this->client->getInternalResponse()->getContent());
        self::assertIsString($sql, 'The dump is not a complete gzip stream.');
        self::assertStringContainsString('CREATE TABLE `attachment`', $sql);
        self::assertStringContainsString('-- Dump completed', $sql);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function index(int $min): array
    {
        $this->client->request('GET', '/api/media/files?min='.$min, server: ['HTTP_X_BACKUP_SECRET' => self::SECRET]);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['files'];
    }

    private function attach(): Attachment
    {
        $ticket = $this->createTicket();
        $image = new Imagick();
        $image->newPseudoImage(400, 300, 'gradient:red-blue');
        $image->setImageFormat('png');

        $path = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $path, $image->getImagesBlob());

        $attachment = static::getContainer()->get(AttachmentUploader::class)->upload(
            $ticket,
            new UploadedFile((string) $path, 'capture.png', 'image/png', test: true),
            $ticket->getCreatedBy(),
            'Capture of a transient state.',
        );

        $this->written[] = $attachment->getStoragePath();

        return $attachment;
    }

    public function testABearerTokenOpensTheApiAndAWrongOneDoesNot(): void
    {
        $this->client->request('GET', '/api/media/files?min=0', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::SECRET]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/media/files?min=0', server: ['HTTP_AUTHORIZATION' => 'Bearer wrong']);
        self::assertResponseStatusCodeSame(404);
    }
}
