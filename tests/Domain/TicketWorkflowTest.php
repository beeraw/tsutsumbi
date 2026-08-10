<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\TicketChange;
use App\Domain\TicketWorkflow;
use App\Entity\Ticket;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Exception\InvalidTicketStateException;
use App\Tests\DomainTestCase;
use ReflectionProperty;

/**
 * The two invariants the model rests on: a closed ticket always says how it
 * ended, an open one never does. Plus the rule that no blockage stays silent.
 */
final class TicketWorkflowTest extends DomainTestCase
{
    private TicketWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = self::getContainer()->get(TicketWorkflow::class);
    }

    public function testANewTicketStartsAsTodoWithoutAResolution(): void
    {
        $ticket = $this->createTicket();

        self::assertSame(TicketStatus::Todo, $ticket->getStatus());
        self::assertNull($ticket->getResolution());
        self::assertNull($ticket->getStartedAt());
        self::assertNull($ticket->getClosedAt());
    }

    public function testClosingWithoutAResolutionIsRefused(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidTicketStateException::class);

        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Done);
    }

    public function testAnOpenStatusCannotCarryAResolution(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidTicketStateException::class);

        $this->apply(
            $ticket,
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::InProgress,
            resolution: TicketResolution::Fixed,
        );
    }

    public function testAResolutionAloneIsRefusedOnAnOpenTicket(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidTicketStateException::class);

        $this->apply($ticket, [TicketChange::RESOLUTION], resolution: TicketResolution::Fixed);
    }

    public function testClosingStampsTheResolutionAndTheClosingDate(): void
    {
        $ticket = $this->createTicket();

        $this->apply(
            $ticket,
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        );

        self::assertSame(TicketStatus::Done, $ticket->getStatus());
        self::assertSame(TicketResolution::Fixed, $ticket->getResolution());
        self::assertNotNull($ticket->getClosedAt());
    }

    public function testReopeningClearsTheResolutionAndTheClosingDate(): void
    {
        $ticket = $this->createTicket();

        $this->apply(
            $ticket,
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        );

        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Todo);

        self::assertSame(TicketStatus::Todo, $ticket->getStatus());
        self::assertNull($ticket->getResolution());
        self::assertNull($ticket->getClosedAt());
    }

    public function testAClosedTicketCanChangeItsResolutionWithoutReopening(): void
    {
        $ticket = $this->createTicket();

        $this->apply(
            $ticket,
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Fixed,
        );

        $this->apply($ticket, [TicketChange::RESOLUTION], resolution: TicketResolution::Duplicate);

        self::assertSame(TicketStatus::Done, $ticket->getStatus());
        self::assertSame(TicketResolution::Duplicate, $ticket->getResolution());
    }

    public function testRepeatingTheClosedStatusKeepsTheResolutionInPlace(): void
    {
        $ticket = $this->createTicket();

        $this->apply(
            $ticket,
            [TicketChange::STATUS, TicketChange::RESOLUTION],
            status: TicketStatus::Done,
            resolution: TicketResolution::Abandoned,
        );

        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Done);

        self::assertSame(TicketResolution::Abandoned, $ticket->getResolution());
    }

    public function testBlockingWithoutAnExplanationIsRefused(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidTicketStateException::class);

        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Blocked);
    }

    public function testBlockingWithABlankNoteIsRefused(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidTicketStateException::class);

        $this->apply($ticket, [TicketChange::STATUS, TicketChange::NOTE], status: TicketStatus::Blocked, note: "  \n ");
    }

    public function testBlockingRecordsTheExplanationAlongsideTheStatus(): void
    {
        $ticket = $this->createTicket();

        $this->apply(
            $ticket,
            [TicketChange::STATUS, TicketChange::NOTE],
            status: TicketStatus::Blocked,
            note: 'Waiting on the OAuth client from the provider.',
        );

        self::assertSame(TicketStatus::Blocked, $ticket->getStatus());
        self::assertCount(1, $ticket->getComments());

        $comment = $ticket->getComments()->first();

        self::assertNotFalse($comment);
        self::assertSame('Waiting on the OAuth client from the provider.', $comment->getBody());
    }

    public function testAFailedBlockLeavesNeitherTheStatusNorACommentBehind(): void
    {
        $ticket = $this->createTicket();

        try {
            $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Blocked);
        } catch (InvalidTicketStateException) {
            // Expected; what matters is what the database looks like afterwards.
        }

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Ticket::class, $ticket->getId());

        self::assertNotNull($reloaded);
        self::assertSame(TicketStatus::Todo, $reloaded->getStatus());
        self::assertCount(0, $reloaded->getComments());
    }

    public function testStayingBlockedDoesNotDemandANoteAgain(): void
    {
        $ticket = $this->createTicket();

        $this->apply($ticket, [TicketChange::STATUS, TicketChange::NOTE], status: TicketStatus::Blocked, note: 'Blocked.');
        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Blocked);

        self::assertSame(TicketStatus::Blocked, $ticket->getStatus());
    }

    public function testTheStartingDateIsStampedOnceAndNeverMovedAgain(): void
    {
        $ticket = $this->createTicket();

        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::InProgress);
        $startedAt = $ticket->getStartedAt();

        self::assertNotNull($startedAt);

        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::Todo);
        $this->apply($ticket, [TicketChange::STATUS], status: TicketStatus::InProgress);

        self::assertEquals($startedAt, $ticket->getStartedAt());
    }

    public function testANoteWithoutAStatusChangeIsJustAComment(): void
    {
        $ticket = $this->createTicket();

        $this->apply($ticket, [TicketChange::NOTE], note: 'VAT stays out of scope.');

        self::assertSame(TicketStatus::Todo, $ticket->getStatus());
        self::assertCount(1, $ticket->getComments());
    }

    public function testADescriptionIsClearedWhenTheKeyIsSentAsNull(): void
    {
        $ticket = $this->createTicket(description: 'Ancienne description');

        $this->workflow->apply(
            $ticket,
            $ticket->getCreatedBy(),
            new TicketChange([TicketChange::DESCRIPTION], description: null),
        );

        self::assertNull($ticket->getDescription());
    }

    /**
     * The invariant is also enforced at flush time, so code that bypasses
     * applyStatus() still cannot write an inconsistent row.
     */
    public function testTheDatabaseRefusesAClosedTicketWithoutAResolution(): void
    {
        $ticket = $this->createTicket();

        $status = new ReflectionProperty(Ticket::class, 'status');
        $status->setValue($ticket, TicketStatus::Done);

        $this->expectException(InvalidTicketStateException::class);

        $this->entityManager->flush();
    }

    /**
     * @param list<string> $provided
     */
    private function apply(
        Ticket $ticket,
        array $provided,
        ?TicketStatus $status = null,
        ?TicketResolution $resolution = null,
        ?string $note = null,
    ): void {
        $this->workflow->apply($ticket, $ticket->getCreatedBy(), new TicketChange(
            $provided,
            status: $status,
            resolution: $resolution,
            note: $note,
        ));
    }
}
