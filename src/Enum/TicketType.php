<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketType: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Chore = 'chore';
    case Refactor = 'refactor';
    case Docs = 'docs';
    case Infra = 'infra';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function translationKey(): string
    {
        return 'ticket.type.'.$this->value;
    }

    /**
     * The abbreviated label, for the ticket list only.
     *
     * A long label — "Fonctionnalité" in French, "Infrastruktur" in German —
     * does not fit a column sized for a screen that shows thirty rows at once,
     * and a type truncated to "FONCTION…" is worse than no type at all. Every
     * locale therefore carries a short form of its own; the full label stays
     * wherever there is room for it: the detail screen and the filters.
     */
    public function shortTranslationKey(): string
    {
        return 'ticket.type_short.'.$this->value;
    }
}
