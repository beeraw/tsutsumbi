<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\TicketFactory;
use App\Entity\Agent;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

abstract class InterfaceTestCase extends WebTestCase
{
    protected const string PASSWORD = 'correct horse battery staple';

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Re-reads an entity after a request.
     *
     * The test client resets the container between requests, which clears the
     * entity manager — anything fetched before a request is detached after it,
     * and asserting on a stale object would assert on nothing.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function reload(string $class, ?int $id): object
    {
        $entity = static::getContainer()->get(EntityManagerInterface::class)->find($class, $id);

        self::assertInstanceOf($class, $entity, sprintf('%s #%s should still exist.', $class, $id ?? 'null'));

        return $entity;
    }

    protected function createUser(string $email = 'jl@example.test'): User
    {
        $user = new User(sprintf('%s.%s', bin2hex(random_bytes(4)), $email));
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD),
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function login(?User $user = null): User
    {
        $user ??= $this->createUser();
        $this->client->loginUser($user);

        return $user;
    }

    protected function createAgent(string $slug = 'claude-code'): Agent
    {
        $agent = new Agent($slug.'-'.bin2hex(random_bytes(3)));

        $this->entityManager->persist($agent);
        $this->entityManager->flush();

        return $agent;
    }

    protected function createProject(string $name = 'harusi'): Project
    {
        $suffix = bin2hex(random_bytes(4));

        $project = new Project(
            mb_strtoupper(substr($name, 0, 4)).substr($suffix, 0, 5),
            $name,
            sprintf('/tmp/tsutsumbi-tests/%s/%s', $suffix, $name),
        );

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    protected function createTicket(
        ?Project $project = null,
        string $title = 'CSV export of statements',
        TicketType $type = TicketType::Feature,
        ?string $description = null,
    ): Ticket {
        return static::getContainer()->get(TicketFactory::class)->create(
            $project ?? $this->createProject(),
            $title,
            $type,
            $this->createAgent(),
            $description,
        );
    }
}
