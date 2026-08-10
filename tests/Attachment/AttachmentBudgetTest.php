<?php

declare(strict_types=1);

namespace App\Tests\Attachment;

use App\Attachment\AttachmentCompressorInterface;
use App\Attachment\AttachmentPolicy;
use App\Attachment\AttachmentStorage;
use App\Attachment\AttachmentUploader;
use App\Attachment\CompressedFile;
use App\Entity\Actor;
use App\Entity\Attachment;
use App\Entity\Ticket;
use App\Exception\AttachmentRejectedException;
use App\Tests\DomainTestCase;
use Imagick;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;
use function dirname;

/**
 * The size rules, tested as size rules.
 */
final class AttachmentBudgetTest extends DomainTestCase
{
    private AttachmentUploader $uploader;

    /**
     * @var list<string>
     */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploader = self::getContainer()->get(AttachmentUploader::class);
    }

    protected function tearDown(): void
    {
        $storage = self::getContainer()->get(AttachmentStorage::class);

        foreach ($this->written as $path) {
            $storage->removePath($path);
        }

        parent::tearDown();
    }

    public function testATicketAtItsQuotaTakesNothingMore(): void
    {
        $ticket = $this->createTicket();

        // A row is enough: the budget is read from what the ticket already
        // records, not from what happens to be on disk.
        $this->recordExistingAttachment($ticket->getCreatedBy(), $ticket, AttachmentPolicy::MAX_TICKET_BYTES - 1024);

        $this->expectException(AttachmentRejectedException::class);
        $this->expectExceptionMessageMatches('/would pass the/');

        $this->uploader->upload($ticket, $this->image(600, 400), $ticket->getCreatedBy(), 'Capture.');
    }

    public function testATicketWithRoomLeftStillTakesOne(): void
    {
        $ticket = $this->createTicket();
        $this->recordExistingAttachment($ticket->getCreatedBy(), $ticket, 1024);

        $attachment = $this->uploader->upload($ticket, $this->image(600, 400), $ticket->getCreatedBy(), 'Capture.');
        $this->written[] = $attachment->getStoragePath();

        self::assertSame('image/webp', $attachment->getMimeType());
    }

    /**
     * The cap on what arrives is checked before the bytes are read, so a file
     * nobody should be sending is refused rather than loaded into memory first.
     */
    public function testAFileTooBigToEvenReadIsRefused(): void
    {
        $ticket = $this->createTicket();

        $path = tempnam(sys_get_temp_dir(), 'tsu');
        $handle = fopen((string) $path, 'wb');
        self::assertNotFalse($handle);
        fseek($handle, AttachmentPolicy::MAX_UPLOAD_BYTES + 1);
        fwrite($handle, "\0");
        fclose($handle);

        $this->expectException(AttachmentRejectedException::class);
        $this->expectExceptionMessageMatches('/above the .* allowed before compression/');

        $this->uploader->upload(
            $ticket,
            new UploadedFile((string) $path, 'huge.png', 'image/png', test: true),
            $ticket->getCreatedBy(),
            'Capture.',
        );
    }

    /**
     * Whether any real image survives WebP at this size is beside the point: the
     * rule is that whatever comes out of compression above the ceiling is
     * refused, and that is what a stub can state plainly.
     */
    public function testWhatIsStillTooBigAfterCompressionIsRefused(): void
    {
        $ticket = $this->createTicket();
        $uploader = new AttachmentUploader(
            $this->entityManager,
            new class implements AttachmentCompressorInterface {
                public function compress(string $contents, string $mediaType): CompressedFile
                {
                    return new CompressedFile(
                        str_repeat('x', AttachmentPolicy::MAX_STORED_BYTES + 1),
                        'image/webp',
                        'webp',
                    );
                }
            },
            self::getContainer()->get(AttachmentStorage::class),
            self::getContainer()->get(TranslatorInterface::class),
        );

        $this->expectException(AttachmentRejectedException::class);
        $this->expectExceptionMessageMatches('/still .* after compression/');

        $uploader->upload($ticket, $this->image(200, 200), $ticket->getCreatedBy(), 'Capture.');
    }

    /**
     * A refusal happens before anything is written, so there is nothing to clean
     * up afterwards.
     */
    public function testARefusedUploadLeavesNoRowAndNoFile(): void
    {
        $ticket = $this->createTicket();
        $storage = self::getContainer()->get(AttachmentStorage::class);
        $before = $this->countStoredFiles($storage);

        $binary = $this->asUpload("\x7fELF\x02\x01\x01".str_repeat("\0", 64), 'tool.bin', 'application/octet-stream');

        try {
            $this->uploader->upload($ticket, $binary, $ticket->getCreatedBy(), 'Output that cannot be replayed.');
            self::fail('An executable is outside the whitelist and must be refused.');
        } catch (AttachmentRejectedException) {
            // Expected. What matters is the state it leaves behind.
        }

        self::assertCount(0, $ticket->getAttachments());
        self::assertSame($before, $this->countStoredFiles($storage));
    }

    private function recordExistingAttachment(Actor $actor, Ticket $ticket, int $size): void
    {
        $this->entityManager->persist(new Attachment(
            $ticket,
            'already-there.webp',
            'image/webp',
            $size,
            '2026/07/'.bin2hex(random_bytes(16)).'.webp',
            str_repeat('0', 64),
            $actor,
        ));
        $this->entityManager->flush();
    }

    private function countStoredFiles(AttachmentStorage $storage): int
    {
        $root = dirname($storage->absolutePath('2026/07/'.str_repeat('a', 32).'.webp'));

        if (!is_dir($root)) {
            return 0;
        }

        return count(glob($root.'/*') ?: []);
    }

    private function image(int $width, int $height): UploadedFile
    {
        $image = new Imagick();
        $image->newPseudoImage($width, $height, 'gradient:red-blue');
        $image->setImageFormat('png');

        return $this->asUpload($image->getImagesBlob(), 'capture.png', 'image/png');
    }

    private function asUpload(string $contents, string $name, string $mediaType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tsu');
        file_put_contents((string) $path, $contents);

        return new UploadedFile((string) $path, $name, $mediaType, test: true);
    }
}
