<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\Actor;
use App\Entity\Comment;
use App\Entity\TicketEvent;
use DateTimeImmutable;

/**
 * One line of a ticket's history: either something somebody wrote, or a set of
 * fields that moved together.
 */
final readonly class TimelineEntry
{
    public const string COMMENT = 'comment';
    public const string CHANGE = 'change';

    /**
     * @param list<TicketEvent> $events
     */
    private function __construct(
        public string $kind,
        public DateTimeImmutable $at,
        public Actor $actor,
        public ?Comment $comment,
        public array $events,
    ) {
    }

    public static function forComment(Comment $comment): self
    {
        return new self(
            self::COMMENT,
            $comment->getCreatedAt(),
            $comment->getAuthor(),
            $comment,
            [],
        );
    }

    /**
     * @param non-empty-list<TicketEvent> $events
     */
    public static function forChanges(array $events): self
    {
        $first = $events[0];

        return new self(
            self::CHANGE,
            $first->getCreatedAt(),
            $first->getActor(),
            null,
            $events,
        );
    }

    public function isComment(): bool
    {
        return self::COMMENT === $this->kind;
    }

    /**
     * Changes come before the comment that explains them when both landed in the
     * same transaction, which is the order they read in.
     */
    public function sortRank(): int
    {
        return self::CHANGE === $this->kind ? 0 : 1;
    }
}
