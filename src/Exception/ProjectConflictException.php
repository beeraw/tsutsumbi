<?php

declare(strict_types=1);

namespace App\Exception;

use DomainException;

use function sprintf;

/**
 * Raised when a change to a project would collide with another one.
 *
 * Path and key are both unique, and both are what somebody else's session or
 * bookmark points at, so a collision is refused rather than silently resolved:
 * generating a free key the way creation does would rename the wrong project
 * from the caller's point of view.
 *
 * Like InvalidTicketStateException, it carries a technical message for the API
 * and a translation key for the interface.
 */
final class ProjectConflictException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly string $field,
    ) {
        parent::__construct($message);
    }

    public static function pathTaken(string $path): self
    {
        return new self(
            sprintf('Another project is already tracked at "%s".', $path),
            'project.conflict.path_taken',
            'path',
        );
    }

    public static function keyTaken(string $key): self
    {
        return new self(
            sprintf('Another project already holds the key "%s".', $key),
            'project.conflict.key_taken',
            'key',
        );
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Which field the interface should attach the error to.
     */
    public function getField(): string
    {
        return $this->field;
    }
}
