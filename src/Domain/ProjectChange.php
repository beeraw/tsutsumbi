<?php

declare(strict_types=1);

namespace App\Domain;

use function in_array;

/**
 * What a PATCH asks to change on a project.
 *
 * Same shape as TicketChange, and for the same reason: the description can be
 * cleared, so null on its own does not say whether the caller meant "empty" or
 * "leave it be". The list of provided fields does.
 */
final readonly class ProjectChange
{
    public const string NAME = 'name';
    public const string DESCRIPTION = 'description';
    public const string PATH = 'path';
    public const string KEY = 'key';

    /**
     * @param list<string> $provided
     */
    public function __construct(
        public array $provided,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $path = null,
        public ?string $key = null,
    ) {
    }

    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->provided;
    }
}
