<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A tracked project, identified by the absolute path of its directory.
 *
 * The path is the real identity: an agent starting a session knows its working
 * directory, not a database key.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
#[ORM\UniqueConstraint(name: 'uniq_project_path', columns: ['path'])]
#[ORM\UniqueConstraint(name: 'uniq_project_key', columns: ['`key`'])]
#[ORM\HasLifecycleCallbacks]
class Project
{
    public const int KEY_MAX_LENGTH = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: '`key`', length: self::KEY_MAX_LENGTH)]
    private string $key;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 500)]
    private string $path;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Highest ticket number handed out so far, never derived from a COUNT().
     *
     * The column is owned by TicketNumberAllocator, which increments it in SQL
     * while holding the row lock. Marking it non-updatable keeps the ORM from
     * writing a stale in-memory value back over a concurrent allocation, so
     * this getter is only ever indicative.
     */
    #[ORM\Column(updatable: false, options: ['default' => 0])]
    private int $lastTicketNumber = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    public function __construct(string $key, string $name, string $path)
    {
        $this->key = $key;
        $this->name = $name;
        $this->path = $path;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getLastTicketNumber(): int
    {
        return $this->lastTicketNumber;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function archive(): void
    {
        $this->archivedAt ??= new DateTimeImmutable();
    }

    public function unarchive(): void
    {
        $this->archivedAt = null;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
