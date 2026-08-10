<?php

declare(strict_types=1);

namespace App\Attachment;

use App\Exception\AttachmentRejectedException;

/**
 * Image or text, the treatment differs; that the bytes shrink does not.
 *
 * The boundary exists so the size policy can be tested for what it is — a rule
 * about a number — rather than through the entropy of a generated image.
 */
interface AttachmentCompressorInterface
{
    /**
     * @throws AttachmentRejectedException
     */
    public function compress(string $contents, string $mediaType): CompressedFile;
}
