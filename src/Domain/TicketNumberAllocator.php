<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Project;
use Doctrine\DBAL\Connection;
use LogicException;

use function sprintf;

/**
 * Hands out the next ticket number for a project.
 *
 * Never a COUNT(): two concurrent calls would read the same count and mint the
 * same number. The increment happens in SQL, so the exclusive row lock the
 * UPDATE takes is what serialises callers, and it is held until the surrounding
 * transaction commits.
 *
 * It takes a Connection rather than the entity manager on purpose — the
 * guarantee it makes is about row locks, and that is what a test has to be able
 * to point two independent connections at.
 */
final readonly class TicketNumberAllocator
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Must run inside a transaction: outside one, the row lock would be released
     * the moment the UPDATE returns and the same number could go out twice.
     */
    public function allocate(Project $project): int
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Ticket numbers must be allocated inside a transaction.');
        }

        $id = $project->getId();

        if (null === $id) {
            throw new LogicException('The project must be persisted before a ticket number is allocated.');
        }

        return $this->allocateFor($id);
    }

    public function allocateFor(int $projectId): int
    {
        $this->connection->executeStatement(
            'UPDATE project SET last_ticket_number = last_ticket_number + 1 WHERE id = :id',
            ['id' => $projectId],
        );

        $number = $this->connection->fetchOne(
            'SELECT last_ticket_number FROM project WHERE id = :id',
            ['id' => $projectId],
        );

        if (false === $number) {
            throw new LogicException(sprintf('Project %d disappeared while allocating a ticket number.', $projectId));
        }

        return (int) $number;
    }
}
