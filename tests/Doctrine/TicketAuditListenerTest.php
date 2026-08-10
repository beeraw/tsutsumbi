<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Domain\TicketChange;
use App\Domain\TicketWorkflow;
use App\Entity\Ticket;
use App\Enum\TicketPriority;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Repository\TicketEventRepository;
use App\Tests\DomainTestCase;

/**
 * The journal is written by a Doctrine listener rather than by callers, so these
 * tests never ask for an event — they change a ticket and check the trace.
 */
final class TicketAuditListenerTest extends DomainTestCase
{
    private TicketWorkflow $workflow;
    private TicketEventRepository $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = self::getContainer()->get(TicketWorkflow::class);
        $this->events = self::getContainer()->get(TicketEventRepository::class);
    }

    public function testCreatingATicketWritesNoEventYet(): void
    {
        $ticket = $this->createTicket();

        self::assertSame([], $this->events->findForTicket($ticket));
    }

    public function testAStatusChangeIsRecordedWithItsBeforeAndAfter(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS],
            status: TicketStatus::InProgress,
        ));

        $events = $this->events->findForTicket($ticket);

        self::assertCount(1, $events);
        self::assertSame('status', $events[0]->getField());
        self::assertSame('todo', $events[0]->getOldValue());
        self::assertSame('in_progress', $events[0]->getNewValue());
    }

    public function testEachChangedFieldGetsItsOwnRow(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::TITLE, TicketChange::PRIORITY, TicketChange::TYPE],
            title: 'CSV and XLSX export of statements',
            type: TicketType::Bug,
            priority: TicketPriority::High,
        ));

        $fields = array_map(
            static fn ($event): string => $event->getField(),
            $this->events->findForTicket($ticket),
        );

        self::assertCount(3, $fields);
        self::assertSame(['title', 'type', 'priority'], $fields);
    }

    public function testAFieldSetToTheValueItAlreadyHadIsNotRecorded(): void
    {
        $ticket = $this->createTicket(title: 'CSV export of statements');

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::TITLE, TicketChange::PRIORITY],
            title: 'CSV export of statements',
            priority: TicketPriority::Normal,
        ));

        self::assertSame([], $this->events->findForTicket($ticket));
    }

    public function testClosingRecordsBothTheStatusAndTheResolution(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        ));

        $events = $this->events->findForTicket($ticket);
        $byField = [];
        foreach ($events as $event) {
            $byField[$event->getField()] = $event;
        }

        self::assertArrayHasKey('status', $byField);
        self::assertArrayHasKey('resolution', $byField);
        self::assertNull($byField['resolution']->getOldValue());
        self::assertSame('fixed', $byField['resolution']->getNewValue());
    }

    public function testReopeningLeavesATraceOfTheResolutionBeingCleared(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        ));

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS],
            status: TicketStatus::InProgress,
        ));

        $cleared = array_values(array_filter(
            $this->events->findForTicket($ticket),
            static fn ($event): bool => 'resolution' === $event->getField() && null === $event->getNewValue(),
        ));

        self::assertCount(1, $cleared);
        self::assertSame('fixed', $cleared[0]->getOldValue());
    }

    public function testADescriptionRewriteIsKeptInFullRatherThanTruncated(): void
    {
        $ticket = $this->createTicket(description: 'court');
        $long = str_repeat('The need is described at length. ', 40);

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::DESCRIPTION],
            description: $long,
        ));

        $events = $this->events->findForTicket($ticket);

        self::assertCount(1, $events);
        self::assertSame($long, $events[0]->getNewValue());
    }

    /**
     * Nothing is authenticated in a console or fixture context, so the journal
     * falls back to the ticket's author instead of dropping the record.
     */
    public function testWithoutAnAuthenticatedActorTheTicketAuthorIsCredited(): void
    {
        $agent = $this->createAgent('claude-review');
        $ticket = $this->createTicket(author: $agent);

        $this->workflow->apply($ticket, $agent, new TicketChange(
            [TicketChange::STATUS],
            status: TicketStatus::InProgress,
        ));

        $events = $this->events->findForTicket($ticket);

        self::assertCount(1, $events);
        self::assertSame($agent->getId(), $events[0]->getActor()->getId());
    }

    public function testTheJournalSurvivesTheEntityManagerBeingCleared(): void
    {
        $ticket = $this->createTicket();

        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            [TicketChange::STATUS],
            status: TicketStatus::InProgress,
        ));

        $id = $ticket->getId();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(Ticket::class, $id);

        self::assertNotNull($reloaded);
        self::assertCount(1, $this->events->findForTicket($reloaded));
    }
}
