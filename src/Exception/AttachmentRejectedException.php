<?php

declare(strict_types=1);

namespace App\Exception;

use App\Attachment\AttachmentPolicy;
use DomainException;

use function sprintf;

/**
 * Raised when a file does not meet the attachment policy.
 *
 * Like the ticket-state refusals, it carries a technical message for the API and
 * a translation key for anything a human reads.
 */
final class AttachmentRejectedException extends DomainException
{
    /**
     * @param array<string, string> $translationParameters
     */
    private function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
    ) {
        parent::__construct($message);
    }

    public static function missingFile(): self
    {
        return new self('No file was sent.', 'attachment.no_file');
    }

    public static function missingReason(): self
    {
        return new self('An attachment must come with the reason it exists.', 'attachment.missing_reason');
    }

    public static function unsupportedMediaType(string $mediaType): self
    {
        return new self(
            sprintf(
                'Media type "%s" is not accepted. Accepted: %s.',
                $mediaType,
                implode(', ', AttachmentPolicy::acceptedMediaTypes()),
            ),
            'attachment.unsupported_media_type',
            ['%type%' => $mediaType],
        );
    }

    public static function uploadTooLarge(int $size): self
    {
        return new self(
            sprintf('The file is %d bytes, above the %d allowed before compression.', $size, AttachmentPolicy::MAX_UPLOAD_BYTES),
            'attachment.upload_too_large',
            ['%limit%' => self::megabytes(AttachmentPolicy::MAX_UPLOAD_BYTES)],
        );
    }

    public static function stillTooLargeAfterCompression(int $size): self
    {
        return new self(
            sprintf('The file is still %d bytes after compression, above the %d allowed.', $size, AttachmentPolicy::MAX_STORED_BYTES),
            'attachment.too_large_after_compression',
            ['%limit%' => self::megabytes(AttachmentPolicy::MAX_STORED_BYTES)],
        );
    }

    public static function ticketBudgetExhausted(int $used, int $incoming): self
    {
        return new self(
            sprintf('The ticket already holds %d bytes; %d more would pass the %d allowed.', $used, $incoming, AttachmentPolicy::MAX_TICKET_BYTES),
            'attachment.ticket_budget_exhausted',
            ['%limit%' => self::megabytes(AttachmentPolicy::MAX_TICKET_BYTES)],
        );
    }

    public static function unreadableImage(): self
    {
        return new self('The file claims to be an image but cannot be read as one.', 'attachment.unreadable_image');
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }

    private static function megabytes(int $bytes): string
    {
        return sprintf('%.0f Mo', $bytes / (1024 * 1024));
    }
}
