<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\TicketFactory;
use App\Entity\Agent;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

abstract class DomainTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createAgent(string $slug = 'claude-code'): Agent
    {
        $agent = new Agent($slug.'-'.bin2hex(random_bytes(3)));

        $this->entityManager->persist($agent);
        $this->entityManager->flush();

        return $agent;
    }

    protected function createProject(string $name = 'maji'): Project
    {
        $suffix = bin2hex(random_bytes(4));

        $project = new Project(
            mb_strtoupper(substr($name, 0, 5)).substr($suffix, 0, 4),
            $name,
            sprintf('/tmp/tsutsumbi-tests/%s/%s', $suffix, $name),
        );

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    protected function createTicket(
        ?Project $project = null,
        ?Agent $author = null,
        string $title = 'CSV export of statements',
        TicketType $type = TicketType::Feature,
        ?string $description = null,
    ): Ticket {
        return self::getContainer()->get(TicketFactory::class)->create(
            $project ?? $this->createProject(),
            $title,
            $type,
            $author ?? $this->createAgent(),
            $description,
        );
    }
}
