<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    /**
     * @phpstan-impure the answer changes as soon as anything creates the agent
     */
    public function findOneBySlug(string $slug): ?Agent
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
