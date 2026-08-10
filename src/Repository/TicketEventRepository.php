<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\TicketEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketEvent>
 */
class TicketEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketEvent::class);
    }

    /**
     * @return list<TicketEvent>
     */
    public function findForTicket(Ticket $ticket): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.ticket = :ticket')
            ->setParameter('ticket', $ticket)
            ->orderBy('e.createdAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
