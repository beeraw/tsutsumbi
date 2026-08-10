<?php

declare(strict_types=1);

namespace App\Tests\Attachment;

use App\Attachment\AttachmentCompressor;
use App\Attachment\AttachmentPolicy;
use App\Exception\AttachmentRejectedException;
use Imagick;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * Compression is mandatory, not opportunistic: an attachment exists because
 * something would be lost otherwise, which is an argument for keeping the
 * information, never the original bytes.
 */
final class AttachmentCompressorTest extends TestCase
{
    public function testAnImageComesBackAsWebp(): void
    {
        $compressed = (new AttachmentCompressor())->compress(self::png(400, 300), 'image/png');

        self::assertSame('image/webp', $compressed->mediaType);
        self::assertSame('webp', $compressed->extension);
        self::assertSame('webp', self::formatOf($compressed->contents));
    }

    public function testAnOversizedImageIsBroughtDownToTheAllowedSide(): void
    {
        $compressed = (new AttachmentCompressor())->compress(self::png(4000, 2000), 'image/png');

        $image = new Imagick();
        $image->readImageBlob($compressed->contents);

        self::assertSame(AttachmentPolicy::IMAGE_LONGEST_SIDE, $image->getImageWidth());
        self::assertSame(800, $image->getImageHeight());
    }

    public function testATallImageIsMeasuredOnItsLongSideToo(): void
    {
        $compressed = (new AttachmentCompressor())->compress(self::png(1000, 3000), 'image/png');

        $image = new Imagick();
        $image->readImageBlob($compressed->contents);

        self::assertSame(AttachmentPolicy::IMAGE_LONGEST_SIDE, $image->getImageHeight());
    }

    public function testASmallImageIsNotStretchedUp(): void
    {
        $compressed = (new AttachmentCompressor())->compress(self::png(320, 200), 'image/png');

        $image = new Imagick();
        $image->readImageBlob($compressed->contents);

        self::assertSame(320, $image->getImageWidth());
    }

    public function testTheResultIsSmallerThanWhatWentIn(): void
    {
        $original = self::png(2400, 1600);
        $compressed = (new AttachmentCompressor())->compress($original, 'image/png');

        self::assertLessThan(strlen($original), $compressed->size());
    }

    /**
     * Camera and screenshot metadata is neither needed nor anybody's business.
     */
    public function testMetadataIsStrippedFromTheImage(): void
    {
        $source = new Imagick();
        $source->newPseudoImage(600, 400, 'gradient:red-blue');
        $source->setImageFormat('jpeg');
        $source->setImageProperty('exif:Make', 'A camera nobody needs to know about');

        $compressed = (new AttachmentCompressor())->compress($source->getImagesBlob(), 'image/jpeg');

        $stored = new Imagick();
        $stored->readImageBlob($compressed->contents);

        self::assertSame([], array_filter(
            $stored->getImageProperties('exif:*'),
            static fn (string $value): bool => str_contains($value, 'camera nobody'),
        ));
    }

    public function testTextIsGzipped(): void
    {
        $log = str_repeat("2026-07-27 an ordinary log line that repeats\n", 200);
        $compressed = (new AttachmentCompressor())->compress($log, 'text/plain');

        self::assertSame('application/gzip', $compressed->mediaType);
        self::assertSame('gz', $compressed->extension);
        self::assertLessThan(strlen($log), $compressed->size());
        self::assertSame($log, gzdecode($compressed->contents));
    }

    public function testTheChecksumIsOverWhatIsActuallyStored(): void
    {
        $compressed = (new AttachmentCompressor())->compress('quelques lignes', 'text/plain');

        self::assertSame(hash('sha256', $compressed->contents), $compressed->checksum());
    }

    public function testATypeOutsideTheWhitelistIsRefused(): void
    {
        $this->expectException(AttachmentRejectedException::class);

        (new AttachmentCompressor())->compress('MZ...', 'application/x-msdownload');
    }

    public function testSomethingClaimingToBeAnImageButIsNotIsRefused(): void
    {
        $this->expectException(AttachmentRejectedException::class);

        (new AttachmentCompressor())->compress('not an image at all', 'image/png');
    }

    private static function png(int $width, int $height): string
    {
        $image = new Imagick();
        $image->newPseudoImage($width, $height, 'gradient:red-blue');
        $image->setImageFormat('png');

        return $image->getImagesBlob();
    }

    private static function formatOf(string $blob): string
    {
        $image = new Imagick();
        $image->readImageBlob($blob);

        return strtolower($image->getImageFormat());
    }
}
