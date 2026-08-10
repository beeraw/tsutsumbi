<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\Comment;
use App\Entity\Ticket;
use App\Entity\TicketEvent;

use function sprintf;

/**
 * Merges the comments and the audit journal into the single chronology the
 * ticket screen shows.
 *
 * Events that landed in the same transaction — a status and the resolution that
 * came with it — are grouped into one line. Read separately they say the same
 * thing twice and bury the comment that explains them.
 */
final readonly class TicketTimelineBuilder
{
    /**
     * @param list<TicketEvent> $events
     *
     * @return list<TimelineEntry>
     */
    public function build(Ticket $ticket, array $events): array
    {
        $entries = [];

        foreach ($this->groupByMoment($events) as $group) {
            $entries[] = TimelineEntry::forChanges($group);
        }

        foreach ($ticket->getComments() as $comment) {
            $entries[] = TimelineEntry::forComment($comment);
        }

        usort(
            $entries,
            static fn (TimelineEntry $a, TimelineEntry $b): int => [$a->at->getTimestamp(), $a->sortRank()]
                <=> [$b->at->getTimestamp(), $b->sortRank()],
        );

        return $entries;
    }

    /**
     * The comments alone, for the places that only need those.
     *
     * @return list<Comment>
     */
    public function comments(Ticket $ticket): array
    {
        return array_values($ticket->getComments()->toArray());
    }

    /**
     * Same second, same actor, and no field seen twice.
     *
     * The timestamp column has second precision, so two calls an agent makes in
     * quick succession share one. The audit listener writes at most one row per
     * field per flush, which is what makes a repeated field a reliable sign that
     * a second change started — without it, "todo → in progress" and
     * "in progress → done" would read as a single moment.
     *
     * @param list<TicketEvent> $events
     *
     * @return list<non-empty-list<TicketEvent>>
     */
    private function groupByMoment(array $events): array
    {
        $groups = [];
        $current = null;
        $currentKey = null;
        $seenFields = [];

        foreach ($events as $event) {
            $key = sprintf(
                '%s#%d',
                $event->getCreatedAt()->format('Y-m-d H:i:s'),
                $event->getActor()->getId() ?? 0,
            );

            if (null === $current || $key !== $currentKey || isset($seenFields[$event->getField()])) {
                if (null !== $current) {
                    $groups[] = $current;
                }

                $current = [];
                $currentKey = $key;
                $seenFields = [];
            }

            $current[] = $event;
            $seenFields[$event->getField()] = true;
        }

        if (null !== $current) {
            $groups[] = $current;
        }

        return $groups;
    }
}
