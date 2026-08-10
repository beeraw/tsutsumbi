<?php

declare(strict_types=1);

namespace App\Attachment;

use function strlen;

/**
 * The bytes that will actually be stored, and what they are.
 */
final readonly class CompressedFile
{
    public function __construct(
        public string $contents,
        public string $mediaType,
        public string $extension,
    ) {
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    public function checksum(): string
    {
        return hash('sha256', $this->contents);
    }
}
