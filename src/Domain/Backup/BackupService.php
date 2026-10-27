<?php

declare(strict_types=1);

namespace App\Domain\Backup;

use App\Attachment\AttachmentStorage;
use App\Repository\AttachmentRepository;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash_equals;
use function is_file;
use function preg_match;

/**
 * The application keeps no backup of its own: an outside job pulls the database dump and the ticket attachments
 * through the backup API, authenticated with a shared secret. An empty secret closes the API altogether.
 *
 * The attachments go out with their storage path, relative to the attachments directory, so that a restore is the
 * dump loaded and the files copied back as they are. They are stored once, compressed, with no variant to regenerate.
 */
class BackupService
{
    public const int FILE_PAGE_SIZE = 100;

    /**
     * What AttachmentStorage generates: nothing else is ever listed, so nothing else is ever served.
     */
    private const string STORAGE_PATH = '/^\d{4}\/\d{2}\/[0-9a-f]{32}\.[a-z0-9]{1,8}$/';

    public function __construct(
        private readonly AttachmentRepository $attachments,
        private readonly AttachmentStorage $storage,
        #[Autowire('%env(default::BACKUP_SECRET)%')]
        private readonly ?string $secret = null,
    ) {
    }

    public function isAuthorized(?string $given): bool
    {
        return null !== $this->secret && '' !== $this->secret && null !== $given && hash_equals($this->secret, $given);
    }

    /**
     * Attachments with an id above $min, oldest first. The caller keeps the highest id it received as its cursor and
     * comes back with it until the list is empty. A file missing from the disk is listed with "exists" false, so that
     * the caller can tell it apart from a file it failed to fetch.
     *
     * @return list<array<string, mixed>>
     */
    public function fileIndex(int $min): array
    {
        $index = [];
        foreach ($this->attachments->findAfterId($min, self::FILE_PAGE_SIZE) as $attachment) {
            $path = $attachment->getStoragePath();
            $index[] = [
                'id' => $attachment->getId(),
                'path' => $path,
                'exists' => is_file($this->storage->absolutePath($path)),
                'size' => $attachment->getSize(),
                // The checksum is the SHA-256 of the stored bytes, which is the file as it sits on the disk.
                'hash' => $attachment->getChecksum(),
                'createdAt' => $attachment->getCreatedAt()->format(DateTimeInterface::ATOM),
            ];
        }

        return $index;
    }

    /**
     * The absolute path of a file given by its storage path, as fileIndex lists it, or null. The path is only taken
     * when it is exactly the one an attachment records: nothing else under the directory can be reached.
     */
    public function resolve(string $path): ?string
    {
        if (1 !== preg_match(self::STORAGE_PATH, $path) || null === $this->attachments->findOneBy(['storagePath' => $path])) {
            return null;
        }

        $absolute = $this->storage->absolutePath($path);

        return is_file($absolute) ? $absolute : null;
    }
}
