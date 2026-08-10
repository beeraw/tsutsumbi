<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Project;
use App\Entity\Ticket;
use DateTimeImmutable;

use const DATE_ATOM;

final readonly class ProjectPresenter
{
    public function __construct(private TicketPresenter $tickets)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Project $project, int $activeTickets, ?DateTimeImmutable $lastActivityAt = null): array
    {
        return [
            'key' => $project->getKey(),
            'name' => $project->getName(),
            'path' => $project->getPath(),
            'description' => $project->getDescription(),
            'activeTickets' => $activeTickets,
            'archived' => $project->isArchived(),
            'createdAt' => $project->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $project->getUpdatedAt()->format(DATE_ATOM),
            'lastActivityAt' => ($lastActivityAt ?? $project->getUpdatedAt())->format(DATE_ATOM),
        ];
    }

    /**
     * The resolve() payload. Handing back the active tickets is deliberate: it
     * primes an agent with the real state of the project at the start of a
     * session, which is what stops it opening a ticket that already exists.
     *
     * @param list<Ticket> $activeTickets
     *
     * @return array<string, mixed>
     */
    public function resolved(Project $project, bool $created, array $activeTickets): array
    {
        return [
            'key' => $project->getKey(),
            'name' => $project->getName(),
            'path' => $project->getPath(),
            'created' => $created,
            'activeTickets' => array_map(
                fn (Ticket $ticket): array => $this->tickets->brief($ticket),
                $activeTickets,
            ),
        ];
    }
}
