<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Actor;
use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketPriority;
use App\Enum\TicketType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TicketFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TicketNumberAllocator $allocator,
    ) {
    }

    /**
     * The number allocation and the ticket insert share one transaction, so a
     * failed insert gives the number back instead of leaving a hole.
     */
    public function create(
        Project $project,
        string $title,
        TicketType $type,
        Actor $author,
        ?string $description = null,
        ?TicketPriority $priority = null,
        ?string $note = null,
    ): Ticket {
        return $this->entityManager->wrapInTransaction(
            function () use ($project, $title, $type, $author, $description, $priority, $note): Ticket {
                $ticket = new Ticket(
                    $project,
                    $this->allocator->allocate($project),
                    $title,
                    $type,
                    $author,
                );

                $ticket->setDescription($description);

                if (null !== $priority) {
                    $ticket->setPriority($priority);
                }

                $this->entityManager->persist($ticket);

                if (null !== $note && '' !== trim($note)) {
                    $this->entityManager->persist(new Comment($ticket, trim($note), $author));
                }

                return $ticket;
            },
        );
    }
}
