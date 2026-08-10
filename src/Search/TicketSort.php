<?php

declare(strict_types=1);

namespace App\Search;

/**
 * The columns of the ticket listing a reader can sort on.
 *
 * Not every column is here. "Opened by" is missing on purpose: actors are one
 * table with two shapes — a human is named by an e-mail column, an agent by a
 * label column — so there is no single field to order by, and inventing one
 * would mean denormalising a display name onto the table for the sake of a
 * heading. The column stays plain text until something else needs that name.
 */
enum TicketSort: string
{
    case Key = 'key';
    case Title = 'title';
    case Type = 'type';
    case Status = 'status';
    case Updated = 'updated';

    /**
     * What the listing shows when nobody has asked for anything: the most
     * recently touched first, which is the question a project screen answers.
     * Its own initial direction below is what makes that the default, so the
     * two cannot drift apart.
     */
    public static function default(): self
    {
        return self::Updated;
    }

    /**
     * The direction a first click on the heading gives.
     *
     * A date wants its newest end first — clicking "Updated" to be shown 2019
     * is a click nobody meant. Everything else reads forwards: A before Z,
     * ticket 1 before ticket 40, "todo" before "done".
     */
    public function initialDirection(): SortDirection
    {
        return self::Updated === $this ? SortDirection::Desc : SortDirection::Asc;
    }

    public function translationKey(): string
    {
        return 'ticket.column.'.('updated' === $this->value ? 'updated_at' : $this->value);
    }
}
