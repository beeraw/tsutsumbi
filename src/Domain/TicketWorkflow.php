<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Actor;
use App\Entity\Comment;
use App\Entity\Ticket;
use App\Exception\InvalidTicketStateException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The single place where a ticket moves. Controllers describe the intent, this
 * applies it, and the invariants hold whatever the caller asked for.
 */
final readonly class TicketWorkflow
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws InvalidTicketStateException
     */
    public function apply(Ticket $ticket, Actor $actor, TicketChange $change): void
    {
        $this->entityManager->wrapInTransaction(function () use ($ticket, $actor, $change): void {
            if ($change->has(TicketChange::TITLE) && null !== $change->title) {
                $ticket->setTitle($change->title);
            }

            if ($change->has(TicketChange::DESCRIPTION)) {
                $ticket->setDescription($change->description);
            }

            if ($change->has(TicketChange::TYPE) && null !== $change->type) {
                $ticket->setType($change->type);
            }

            if ($change->has(TicketChange::PRIORITY) && null !== $change->priority) {
                $ticket->setPriority($change->priority);
            }

            $note = $change->trimmedNote();
            $this->applyStatusChange($ticket, $change, '' !== $note);

            // The comment lands in the same transaction as the status change, so
            // a blocked ticket can never exist without its explanation.
            if ('' !== $note) {
                $this->entityManager->persist(new Comment($ticket, $note, $actor));
            }
        });
    }

    private function applyStatusChange(Ticket $ticket, TicketChange $change, bool $hasNote): void
    {
        $requested = $change->has(TicketChange::STATUS) ? $change->status : null;
        $hasResolution = $change->has(TicketChange::RESOLUTION);

        if (null === $requested && !$hasResolution) {
            return;
        }

        $status = $requested ?? $ticket->getStatus();
        $isTransition = $status !== $ticket->getStatus();

        if ($isTransition && $status->requiresNote() && !$hasNote) {
            throw InvalidTicketStateException::noteRequired($status);
        }

        // Without an explicit resolution: keep the current one while the status
        // stands still, drop it as soon as the status moves. That is what makes
        // {"status": "todo"} on a closed ticket a reopening.
        $resolution = $hasResolution
            ? $change->resolution
            : ($isTransition ? null : $ticket->getResolution());

        $ticket->applyStatus($status, $resolution);
    }
}
