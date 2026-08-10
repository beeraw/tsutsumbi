<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /**
     * Single indexed lookup: this is what the selector half of the token buys.
     */
    public function findOneBySelector(string $selector): ?ApiToken
    {
        return $this->findOneBy(['selector' => $selector]);
    }
}
