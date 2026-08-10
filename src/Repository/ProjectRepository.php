<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * The path is the real identity, so this is the lookup resolve() relies on.
     * The caller is responsible for having normalised the path first.
     */
    public function findOneByPath(string $path): ?Project
    {
        return $this->findOneBy(['path' => $path]);
    }

    public function findOneByKey(string $key): ?Project
    {
        return $this->findOneBy(['key' => $key]);
    }

    public function keyExists(string $key): bool
    {
        return null !== $this->findOneByKey($key);
    }

    /**
     * Projects with their active and blocked ticket counts and last ticket
     * activity, ordered by activity descending — what the dashboard, the
     * navigation rail and GET /api/projects show.
     *
     * The blocked count is separate on purpose: the number of active tickets
     * says how much is in flight, not whether anything is stuck, and stuck is
     * the thing worth spotting from across the room.
     *
     * @return list<array{project: Project, activeTickets: int, blockedTickets: int, lastActivityAt: DateTimeImmutable}>
     */
    public function findAllWithActivity(bool $includeArchived = false, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p AS project')
            // DISTINCT matters: joining the ticket table three times multiplies
            // rows, and a plain COUNT would report the size of that product.
            ->addSelect('COUNT(DISTINCT active.id) AS activeTickets')
            ->addSelect('COUNT(DISTINCT blocked.id) AS blockedTickets')
            ->addSelect('COALESCE(MAX(every.updatedAt), p.updatedAt) AS lastActivityAt')
            ->leftJoin(Ticket::class, 'every', 'WITH', 'every.project = p')
            ->leftJoin(
                Ticket::class,
                'active',
                'WITH',
                'active.project = p AND active.status IN (:activeStatuses)',
            )
            ->leftJoin(
                Ticket::class,
                'blocked',
                'WITH',
                'blocked.project = p AND blocked.status = :blockedStatus',
            )
            ->setParameter('activeStatuses', TicketStatus::activeValues())
            ->setParameter('blockedStatus', TicketStatus::Blocked->value)
            ->groupBy('p.id')
            ->orderBy('lastActivityAt', 'DESC')
        ;

        if (!$includeArchived) {
            $qb->andWhere('p.archivedAt IS NULL');
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        /** @var list<array{project: Project, activeTickets: int|string, blockedTickets: int|string, lastActivityAt: DateTimeImmutable|string}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(
            static fn (array $row): array => [
                'project' => $row['project'],
                'activeTickets' => (int) $row['activeTickets'],
                'blockedTickets' => (int) $row['blockedTickets'],
                'lastActivityAt' => $row['lastActivityAt'] instanceof DateTimeImmutable
                    ? $row['lastActivityAt']
                    : new DateTimeImmutable((string) $row['lastActivityAt']),
            ],
            $rows,
        );
    }
}
