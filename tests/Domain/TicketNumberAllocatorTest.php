<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\TicketNumberAllocator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Types\Types;
use LogicException;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Two independent connections, on purpose: the guarantee under test is a row
 * lock, and a single connection can never observe one.
 *
 * This test deliberately works outside the transaction the test suite wraps
 * everything else in — a rolled-back transaction is invisible to a second
 * connection, which is exactly what would make the assertion meaningless.
 */
final class TicketNumberAllocatorTest extends KernelTestCase
{
    private Connection $first;
    private Connection $second;
    private int $projectId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->first = $this->openConnection();
        $this->second = $this->openConnection();
        $this->projectId = $this->insertProject();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->first->delete('project', ['id' => $this->projectId]);

        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testNumbersStartAtOneAndFollowEachOther(): void
    {
        $allocator = new TicketNumberAllocator($this->first);

        $this->first->beginTransaction();
        $numbers = [$allocator->allocateFor($this->projectId), $allocator->allocateFor($this->projectId), $allocator->allocateFor($this->projectId)];
        $this->first->commit();

        self::assertSame([1, 2, 3], $numbers);
    }

    /**
     * The heart of it: while one transaction holds the project row, the other
     * cannot read the counter at all, so it cannot mint a duplicate number.
     */
    public function testAConcurrentAllocationBlocksUntilTheFirstOneCommits(): void
    {
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->first->beginTransaction();
        $firstNumber = (new TicketNumberAllocator($this->first))->allocateFor($this->projectId);

        $this->second->beginTransaction();

        try {
            (new TicketNumberAllocator($this->second))->allocateFor($this->projectId);
            self::fail('The second allocation should have been blocked by the row lock.');
        } catch (LockWaitTimeoutException) {
            // Exactly what the lock is for.
        }

        $this->second->rollBack();
        $this->first->commit();

        $this->second->beginTransaction();
        $secondNumber = (new TicketNumberAllocator($this->second))->allocateFor($this->projectId);
        $this->second->commit();

        self::assertSame(1, $firstNumber);
        self::assertSame(2, $secondNumber, 'The waiting caller must get the next number, never a duplicate.');
    }

    public function testSequentialTransactionsNeverRepeatANumber(): void
    {
        $numbers = [];

        for ($i = 0; $i < 20; ++$i) {
            $connection = 0 === $i % 2 ? $this->first : $this->second;
            $connection->beginTransaction();
            $numbers[] = (new TicketNumberAllocator($connection))->allocateFor($this->projectId);
            $connection->commit();
        }

        self::assertSame(range(1, 20), $numbers);
        self::assertCount(20, array_unique($numbers));
    }

    public function testItRefusesToAllocateOutsideATransaction(): void
    {
        $this->expectException(LogicException::class);

        (new TicketNumberAllocator($this->first))->allocate(
            (new ReflectionClass(\App\Entity\Project::class))->newInstanceWithoutConstructor(),
        );
    }

    /**
     * A fresh connection built from the same credentials, bypassing the driver
     * the test suite substitutes to keep everything in one rolled-back
     * transaction.
     */
    private function openConnection(): Connection
    {
        $params = self::getContainer()->get(Connection::class)->getParams();

        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $params['host'] ?? '127.0.0.1',
            'port' => $params['port'] ?? 3306,
            'user' => $params['user'] ?? 'root',
            'password' => $params['password'] ?? null,
            'dbname' => $params['dbname'] ?? null,
            'charset' => 'utf8mb4',
        ]);
    }

    private function insertProject(): int
    {
        $suffix = bin2hex(random_bytes(4));

        $this->first->insert('project', [
            '`key`' => 'ALLOC'.substr($suffix, 0, 4),
            'name' => 'allocator test',
            'path' => '/tmp/tsutsumbi-tests/'.$suffix.'/alloc',
            'last_ticket_number' => 0,
            'created_at' => new DateTimeImmutable(),
            'updated_at' => new DateTimeImmutable(),
        ], [
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ]);

        return (int) $this->first->lastInsertId();
    }
}
