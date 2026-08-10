<?php

declare(strict_types=1);

namespace App\Attachment;

use App\Entity\Attachment;
use DateTimeImmutable;
use LogicException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

use function dirname;
use function sprintf;

/**
 * Files live outside the web root and are named by this class, never by whoever
 * uploaded them.
 *
 * A client-supplied filename is a client-supplied path: it decides where the
 * bytes land and what the server will later serve. The stored name is random,
 * the extension comes from what the compressor actually produced, and the
 * original name is kept in the database for display only.
 */
final readonly class AttachmentStorage
{
    /**
     * Matches what generatePath() produces, and nothing else. The path comes
     * from our own database, so this is a second lock on a door that should
     * already be shut.
     */
    private const string SAFE_PATH = '/^\d{4}\/\d{2}\/[0-9a-f]{32}\.[a-z0-9]{1,8}$/';

    public function __construct(
        #[Autowire('%app.attachments_dir%')]
        private string $rootDir,
        private Filesystem $filesystem,
    ) {
    }

    public function store(CompressedFile $file): string
    {
        $path = $this->generatePath($file->extension);
        $absolute = $this->absolutePath($path);

        $this->filesystem->mkdir(dirname($absolute));
        $this->filesystem->dumpFile($absolute, $file->contents);

        return $path;
    }

    public function read(Attachment $attachment): string
    {
        $absolute = $this->absolutePath($this->assertSafe($attachment->getStoragePath()));

        if (!is_file($absolute)) {
            throw new RuntimeException(sprintf('Attachment "%s" is recorded but missing on disk.', $attachment->getStoragePath()));
        }

        $contents = file_get_contents($absolute);

        if (false === $contents) {
            throw new RuntimeException(sprintf('Attachment "%s" cannot be read.', $attachment->getStoragePath()));
        }

        return $contents;
    }

    public function remove(Attachment $attachment): void
    {
        $this->removePath($attachment->getStoragePath());
    }

    /**
     * Used to clear the bytes back out when the row that was to point at them
     * never made it in.
     */
    public function removePath(string $relativePath): void
    {
        $this->filesystem->remove($this->absolutePath($relativePath));
    }

    public function absolutePath(string $relativePath): string
    {
        return rtrim($this->rootDir, '/').'/'.$this->assertSafe($relativePath);
    }

    private function generatePath(string $extension): string
    {
        $now = new DateTimeImmutable();

        return sprintf('%s/%s/%s.%s', $now->format('Y'), $now->format('m'), bin2hex(random_bytes(16)), $extension);
    }

    private function assertSafe(string $relativePath): string
    {
        if (1 !== preg_match(self::SAFE_PATH, $relativePath)) {
            throw new LogicException(sprintf('Refusing to touch "%s": not a storage path this class produced.', $relativePath));
        }

        return $relativePath;
    }
}
