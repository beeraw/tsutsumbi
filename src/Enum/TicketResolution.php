<?php

declare(strict_types=1);

namespace App\Enum;

use function in_array;

/**
 * How the work ended. Only ever set on a closed ticket.
 */
enum TicketResolution: string
{
    /** Done, and the outcome matches the intent. */
    case Fixed = 'fixed';
    /** Decided against doing it. */
    case WontDo = 'wont_do';
    /** Started, then left behind. */
    case Abandoned = 'abandoned';
    /** Covered by another ticket, referenced in a comment. */
    case Duplicate = 'duplicate';
    /** The need disappeared along the way. */
    case Obsolete = 'obsolete';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Closing this way is a judgement call, so it always deserves an explanation.
     */
    public function deservesExplanation(): bool
    {
        return in_array($this, [self::WontDo, self::Abandoned], true);
    }

    public function translationKey(): string
    {
        return 'ticket.resolution.'.$this->value;
    }
}
