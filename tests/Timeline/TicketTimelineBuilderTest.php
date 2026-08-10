<?php

declare(strict_types=1);

namespace App\Tests\Timeline;

use App\Domain\TicketChange;
use App\Domain\TicketWorkflow;
use App\Entity\Comment;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Repository\TicketEventRepository;
use App\Tests\DomainTestCase;
use App\Timeline\TicketTimelineBuilder;
use App\Timeline\TimelineEntry;

final class TicketTimelineBuilderTest extends DomainTestCase
{
    private TicketTimelineBuilder $builder;
    private TicketWorkflow $workflow;
    private TicketEventRepository $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = self::getContainer()->get(TicketTimelineBuilder::class);
        $this->workflow = self::getContainer()->get(TicketWorkflow::class);
        $this->events = self::getContainer()->get(TicketEventRepository::class);
    }

    public function testAFreshTicketHasAnEmptyTimeline(): void
    {
        $ticket = $this->createTicket();

        self::assertSame([], $this->builder->build($ticket, $this->events->findForTicket($ticket)));
    }

    /**
     * A status and the resolution that came with it are one moment, not two.
     */
    public function testFieldsChangedTogetherBecomeASingleEntry(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        ));

        $timeline = $this->builder->build($ticket, $this->events->findForTicket($ticket));

        self::assertCount(1, $timeline);
        self::assertCount(2, $timeline[0]->events);
    }

    /**
     * The timestamp column has second precision, so two calls in quick
     * succession share one. A field seen twice is what tells them apart —
     * without it, "todo → in progress" and "in progress → done" would read as a
     * single moment.
     */
    public function testTwoChangesToTheSameFieldStaySeparateEntries(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS],
            status: TicketStatus::InProgress,
        ));
        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        ));

        $timeline = $this->builder->build($ticket, $this->events->findForTicket($ticket));

        self::assertCount(2, $timeline);
        self::assertSame('todo', $timeline[0]->events[0]->getOldValue());
        self::assertSame('in_progress', $timeline[1]->events[0]->getOldValue());
    }

    public function testACommentAppearsAlongsideTheChanges(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS, TicketChange::NOTE],
            status: TicketStatus::Blocked,
            note: 'Le certificat wildcard manque.',
        ));

        $timeline = $this->builder->build($ticket, $this->events->findForTicket($ticket));

        self::assertCount(2, $timeline);
        self::assertSame(TimelineEntry::CHANGE, $timeline[0]->kind);
        self::assertSame(TimelineEntry::COMMENT, $timeline[1]->kind);
    }

    /**
     * The change comes first: read the other way round, the explanation lands
     * before the thing it explains.
     */
    public function testAChangeComesBeforeTheCommentThatExplainsIt(): void
    {
        $ticket = $this->createTicket();

        $this->entityManager->persist(new Comment($ticket, 'Une note libre.', $ticket->getCreatedBy()));
        $this->entityManager->flush();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS, TicketChange::NOTE],
            status: TicketStatus::Blocked,
            note: 'Blocked by an external dependency.',
        ));

        $timeline = $this->builder->build($ticket, $this->events->findForTicket($ticket));
        $kinds = array_map(static fn (TimelineEntry $entry): string => $entry->kind, $timeline);

        self::assertSame(
            [TimelineEntry::CHANGE, TimelineEntry::COMMENT, TimelineEntry::COMMENT],
            $kinds,
        );
    }
}
