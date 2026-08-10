<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Project;

/**
 * The public ticket identifier, MAJI-42: a project key and a per-project number.
 *
 * It is derived, never stored, so reading one back means parsing it.
 */
final readonly class TicketKey
{
    private function __construct(
        public string $projectKey,
        public int $number,
    ) {
    }

    public function __toString(): string
    {
        return $this->projectKey.'-'.$this->number;
    }

    public static function tryParse(string $key): ?self
    {
        if (1 !== preg_match('/^([A-Z0-9]{1,'.Project::KEY_MAX_LENGTH.'})-([1-9]\d*)$/', strtoupper(trim($key)), $matches)) {
            return null;
        }

        return new self($matches[1], (int) $matches[2]);
    }
}
