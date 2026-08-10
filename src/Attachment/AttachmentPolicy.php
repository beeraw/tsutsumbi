<?php

declare(strict_types=1);

namespace App\Attachment;

use function array_key_exists;

/**
 * What may be attached, and how much of it.
 *
 * The list is short on purpose. An attachment is only ever justified when the
 * information would be lost for good otherwise — a capture of a transient state,
 * the output of a tool that cannot be run again. Anything already in the
 * repository, anything a link would cover, anything reproducible: described in a
 * comment instead. A permissive whitelist would quietly turn the exception into
 * the habit.
 */
final class AttachmentPolicy
{
    /**
     * Detected server-side from the bytes, never taken from the request.
     *
     * @var array<string, string> media type to the treatment it receives
     */
    public const array ACCEPTED = [
        'image/png' => self::TREATMENT_IMAGE,
        'image/jpeg' => self::TREATMENT_IMAGE,
        'image/webp' => self::TREATMENT_IMAGE,
        'image/gif' => self::TREATMENT_IMAGE,
        'text/plain' => self::TREATMENT_TEXT,
        'text/csv' => self::TREATMENT_TEXT,
        'text/markdown' => self::TREATMENT_TEXT,
        'text/x-log' => self::TREATMENT_TEXT,
        'application/json' => self::TREATMENT_TEXT,
        'application/xml' => self::TREATMENT_TEXT,
        'text/xml' => self::TREATMENT_TEXT,
    ];

    public const string TREATMENT_IMAGE = 'image';
    public const string TREATMENT_TEXT = 'text';

    /**
     * Per attachment, after compression.
     */
    public const int MAX_STORED_BYTES = 2 * 1024 * 1024;

    /**
     * Per ticket, all attachments together.
     */
    public const int MAX_TICKET_BYTES = 20 * 1024 * 1024;

    /**
     * Before compression. Bounds the work, so a file nobody should be sending
     * is refused before it is read into memory rather than after.
     */
    public const int MAX_UPLOAD_BYTES = 32 * 1024 * 1024;

    public const int IMAGE_LONGEST_SIDE = 1600;
    public const int IMAGE_QUALITY = 60;

    /**
     * @return list<string>
     */
    public static function acceptedMediaTypes(): array
    {
        return array_keys(self::ACCEPTED);
    }

    public static function treatmentFor(string $mediaType): ?string
    {
        return self::ACCEPTED[$mediaType] ?? null;
    }

    public static function accepts(string $mediaType): bool
    {
        return array_key_exists($mediaType, self::ACCEPTED);
    }
}
