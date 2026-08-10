<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use RuntimeException;

use function is_string;
use function sprintf;

/**
 * Finds the agent a token speaks for, creating it if the slug is unknown.
 *
 * Creating on the fly is deliberate: an agent can take on a new role —
 * claude-review after claude-code — without anyone provisioning it first.
 */
final readonly class AgentRegistrar
{
    public function __construct(
        private AgentRepository $agents,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrCreate(string $slug): Agent
    {
        $agent = $this->agents->findOneBySlug($slug);

        if (null !== $agent) {
            return $agent;
        }

        $this->insert($slug);

        $agent = $this->agents->findOneBySlug($slug);

        if (null === $agent) {
            throw new RuntimeException(sprintf('Agent "%s" was inserted, yet it cannot be read back.', $slug));
        }

        return $agent;
    }

    /**
     * The row goes in through DBAL rather than the ORM on purpose: this runs
     * inside the authenticator, and a duplicate key on a flush would close the
     * entity manager for the rest of the request. At the connection level, a
     * losing race is just an exception to swallow — the row the winner inserted
     * is then read back normally.
     */
    private function insert(string $slug): void
    {
        $metadata = $this->entityManager->getClassMetadata(Agent::class);
        $discriminatorColumn = $metadata->discriminatorColumn;

        if (null === $discriminatorColumn) {
            throw new LogicException('Agent is expected to be mapped with a discriminator column.');
        }

        $discriminatorValue = array_search(Agent::class, $metadata->discriminatorMap, true);

        if (!is_string($discriminatorValue)) {
            throw new LogicException('Agent is expected to appear in the actor discriminator map.');
        }

        try {
            $this->entityManager->getConnection()->insert(
                $metadata->getTableName(),
                [
                    $discriminatorColumn->name => $discriminatorValue,
                    $metadata->getColumnName('createdAt') => new DateTimeImmutable(),
                    $metadata->getColumnName('slug') => $slug,
                    $metadata->getColumnName('label') => Agent::labelFromSlug($slug),
                    $metadata->getColumnName('color') => Agent::colorFromSlug($slug),
                ],
                [
                    $discriminatorColumn->name => Types::STRING,
                    $metadata->getColumnName('createdAt') => Types::DATETIME_IMMUTABLE,
                    $metadata->getColumnName('slug') => Types::STRING,
                    $metadata->getColumnName('label') => Types::STRING,
                    $metadata->getColumnName('color') => Types::STRING,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            // Two calls from the same new agent arrived at once; whichever lost
            // simply adopts the row the other one inserted.
        }
    }
}
