<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where the work stands. Independent from the resolution, which says how it ended.
 */
enum TicketStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isActive()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Active statuses are the ones the default views show.
     */
    public function isActive(): bool
    {
        return self::Done !== $this;
    }

    /**
     * A closed status carries a resolution; an active one never does.
     */
    public function isClosed(): bool
    {
        return self::Done === $this;
    }

    /**
     * Reaching this status requires an explanation, so a comment is mandatory.
     */
    public function requiresNote(): bool
    {
        return self::Blocked === $this;
    }

    public function translationKey(): string
    {
        return 'ticket.status.'.$this->value;
    }
}
