<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attachment>
 */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /**
     * @return list<Attachment>
     */
    public function findAfterId(int $min, int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.id > :min')
            ->setParameter('min', $min)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
