<?php

declare(strict_types=1);

namespace App\Attachment;

use App\Exception\AttachmentRejectedException;
use Imagick;
use ImagickException;

/**
 * Compression is mandatory, not opportunistic.
 *
 * An attachment exists because something would be lost otherwise — that is an
 * argument for keeping the information, never for keeping the original bytes.
 * Images become WebP at a modest quality and stop at 1600 pixels on the long
 * side; text and logs are gzipped. What comes out is what gets stored.
 */
final readonly class AttachmentCompressor implements AttachmentCompressorInterface
{
    /**
     * @throws AttachmentRejectedException
     */
    public function compress(string $contents, string $mediaType): CompressedFile
    {
        return match (AttachmentPolicy::treatmentFor($mediaType)) {
            AttachmentPolicy::TREATMENT_IMAGE => $this->toWebp($contents),
            AttachmentPolicy::TREATMENT_TEXT => $this->toGzip($contents, $mediaType),
            default => throw AttachmentRejectedException::unsupportedMediaType($mediaType),
        };
    }

    private function toWebp(string $contents): CompressedFile
    {
        try {
            $image = new Imagick();
            $image->readImageBlob($contents);
        } catch (ImagickException) {
            throw AttachmentRejectedException::unreadableImage();
        }

        // An animated GIF would otherwise be written frame by frame; the first
        // frame is what a screenshot of a transient state is about anyway.
        $image = $image->coalesceImages();
        $image->setFirstIterator();

        $longest = max($image->getImageWidth(), $image->getImageHeight());

        if ($longest > AttachmentPolicy::IMAGE_LONGEST_SIDE) {
            $image->resizeImage(
                $image->getImageWidth() >= $image->getImageHeight() ? AttachmentPolicy::IMAGE_LONGEST_SIDE : 0,
                $image->getImageWidth() >= $image->getImageHeight() ? 0 : AttachmentPolicy::IMAGE_LONGEST_SIDE,
                Imagick::FILTER_LANCZOS,
                1,
            );
        }

        // Camera and screenshot metadata is neither needed nor anybody's
        // business; stripping it also takes a bite out of the size.
        $image->stripImage();
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality(AttachmentPolicy::IMAGE_QUALITY);

        $webp = $image->getImageBlob();
        $image->clear();

        return new CompressedFile($webp, 'image/webp', 'webp');
    }

    private function toGzip(string $contents, string $mediaType): CompressedFile
    {
        $gzipped = gzencode($contents, 9);

        if (false === $gzipped) {
            throw AttachmentRejectedException::unsupportedMediaType($mediaType);
        }

        return new CompressedFile($gzipped, 'application/gzip', 'gz');
    }
}
