<?php

declare(strict_types=1);

namespace App\Attachment;

use App\Entity\Actor;
use App\Entity\Attachment;
use App\Entity\Comment;
use App\Entity\Ticket;
use App\Exception\AttachmentRejectedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final readonly class AttachmentUploader
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttachmentCompressorInterface $compressor,
        private AttachmentStorage $storage,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The reason is not decoration. An attachment is an exception to the rule
     * that everything is described in text, and the reason is what makes that
     * exception reviewable six months later — so it is recorded as a comment on
     * the ticket, in the same transaction as the file.
     *
     * @throws AttachmentRejectedException
     */
    public function upload(Ticket $ticket, UploadedFile $file, Actor $actor, string $reason): Attachment
    {
        $size = $file->getSize();

        if (false === $size || $size > AttachmentPolicy::MAX_UPLOAD_BYTES) {
            throw AttachmentRejectedException::uploadTooLarge(false === $size ? 0 : $size);
        }

        $path = $file->getRealPath();

        if (false === $path || !is_readable($path)) {
            throw AttachmentRejectedException::missingFile();
        }

        // Guessed from the bytes. The Content-Type of a multipart part is
        // whatever the client felt like writing.
        $mediaType = MimeTypes::getDefault()->guessMimeType($path) ?? 'application/octet-stream';

        if (!AttachmentPolicy::accepts($mediaType)) {
            throw AttachmentRejectedException::unsupportedMediaType($mediaType);
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw AttachmentRejectedException::missingFile();
        }

        $compressed = $this->compressor->compress($contents, $mediaType);

        if ($compressed->size() > AttachmentPolicy::MAX_STORED_BYTES) {
            throw AttachmentRejectedException::stillTooLargeAfterCompression($compressed->size());
        }

        $used = $ticket->getAttachmentsTotalSize();

        if ($used + $compressed->size() > AttachmentPolicy::MAX_TICKET_BYTES) {
            throw AttachmentRejectedException::ticketBudgetExhausted($used, $compressed->size());
        }

        $storagePath = $this->storage->store($compressed);

        try {
            return $this->entityManager->wrapInTransaction(
                function () use ($ticket, $file, $actor, $reason, $compressed, $storagePath): Attachment {
                    $attachment = new Attachment(
                        $ticket,
                        $this->displayName($file),
                        $compressed->mediaType,
                        $compressed->size(),
                        $storagePath,
                        $compressed->checksum(),
                        $actor,
                    );

                    $this->entityManager->persist($attachment);
                    // Translated once, here: the comment is stored text, frozen
                    // at upload time. Rendering it later would re-read a locale
                    // that has nothing to do with the moment it was written.
                    $this->entityManager->persist(new Comment(
                        $ticket,
                        $this->translator->trans('attachment.comment', [
                            '%name%' => $attachment->getOriginalFilename(),
                            '%reason%' => trim($reason),
                        ]),
                        $actor,
                    ));

                    return $attachment;
                },
            );
        } catch (Throwable $failure) {
            // The bytes are already on disk; a failed insert would leave them
            // there with nothing pointing at them.
            $this->removeOrphan($storagePath);

            throw $failure;
        }
    }

    /**
     * Kept for display only, and cut down to what a filename can be. It never
     * decides where anything is written.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());
        $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name) ?? '';
        $name = trim($name, '._-');

        return '' === $name ? 'attachment' : mb_substr($name, 0, 255);
    }

    private function removeOrphan(string $storagePath): void
    {
        try {
            $this->storage->removePath($storagePath);
        } catch (Throwable) {
            // Nothing useful to do here: the upload already failed, and the
            // stray file is a housekeeping matter, not a reason to hide why.
        }
    }
}
