<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketPriority;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Exception\InvalidTicketStateException;
use App\Repository\TicketRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A functional unit of work: a feature, a fix, a chore — never a technical step.
 */
#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
#[ORM\UniqueConstraint(name: 'uniq_ticket_project_number', columns: ['project_id', 'number'])]
#[ORM\Index(name: 'idx_ticket_status', columns: ['status'])]
#[ORM\Index(name: 'idx_ticket_updated_at', columns: ['updated_at'])]
#[ORM\Index(name: 'ft_ticket_search', columns: ['title', 'description'], flags: ['fulltext'])]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    /**
     * Fields whose changes are written to the audit journal.
     *
     * @var list<string>
     */
    public const array AUDITED_FIELDS = ['title', 'description', 'type', 'status', 'resolution', 'priority'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    /**
     * Sequential within the project, starting at 1.
     */
    #[ORM\Column]
    private int $number;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TicketType::class)]
    private TicketType $type;

    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::Todo;

    #[ORM\Column(length: 20, nullable: true, enumType: TicketResolution::class)]
    private ?TicketResolution $resolution = null;

    #[ORM\Column(length: 20, enumType: TicketPriority::class)]
    private TicketPriority $priority = TicketPriority::Normal;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Actor $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * First time the ticket moved to in_progress.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'ticket', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $comments;

    /**
     * @var Collection<int, Attachment>
     */
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'ticket', cascade: ['persist'])]
    private Collection $attachments;

    public function __construct(Project $project, int $number, string $title, TicketType $type, Actor $createdBy)
    {
        $this->project = $project;
        $this->number = $number;
        $this->title = $title;
        $this->type = $type;
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->comments = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * The public identifier, e.g. MAJI-42. Derived, never stored.
     */
    public function getKey(): string
    {
        return $this->project->getKey().'-'.$this->number;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): TicketType
    {
        return $this->type;
    }

    public function setType(TicketType $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function getResolution(): ?TicketResolution
    {
        return $this->resolution;
    }

    public function getPriority(): TicketPriority
    {
        return $this->priority;
    }

    public function setPriority(TicketPriority $priority): void
    {
        $this->priority = $priority;
    }

    public function getCreatedBy(): Actor
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): void
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): void
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }
    }

    public function getAttachmentsTotalSize(): int
    {
        $total = 0;
        foreach ($this->attachments as $attachment) {
            $total += $attachment->getSize();
        }

        return $total;
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * The single door through which status and resolution ever change.
     *
     * Keeping both axes in one call is what makes the invariant expressible:
     * a closed ticket carries a resolution, an open one never does.
     *
     * @throws InvalidTicketStateException
     */
    public function applyStatus(TicketStatus $status, ?TicketResolution $resolution, ?DateTimeImmutable $at = null): void
    {
        self::assertConsistent($status, $resolution);

        $at ??= new DateTimeImmutable();
        $wasClosed = $this->status->isClosed();

        $this->status = $status;
        $this->resolution = $resolution;

        if (TicketStatus::InProgress === $status && null === $this->startedAt) {
            $this->startedAt = $at;
        }

        if ($status->isClosed()) {
            $this->closedAt ??= $at;
        } elseif ($wasClosed) {
            // Reopening wipes every trace of the closure.
            $this->closedAt = null;
        }
    }

    /**
     * @throws InvalidTicketStateException
     */
    public static function assertConsistent(TicketStatus $status, ?TicketResolution $resolution): void
    {
        if ($status->isClosed() && null === $resolution) {
            throw InvalidTicketStateException::resolutionRequired();
        }

        if (!$status->isClosed() && null !== $resolution) {
            throw InvalidTicketStateException::resolutionForbidden($status);
        }
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
