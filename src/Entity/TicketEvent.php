<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TicketEventRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per changed field. This is the table that answers "what moved on this
 * project last week" without depending on any conversation history.
 *
 * Written by the audit listener only, never from a controller.
 */
#[ORM\Entity(repositoryClass: TicketEventRepository::class)]
#[ORM\Table(name: 'ticket_event')]
#[ORM\Index(name: 'idx_event_ticket', columns: ['ticket_id'])]
#[ORM\Index(name: 'idx_event_created_at', columns: ['created_at'])]
class TicketEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ticket::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(length: 40)]
    private string $field;

    /**
     * Stored as text rather than a short string: a description rewrite is a
     * legitimate audited change, and truncating it would lose the very thing
     * the journal exists to keep.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $oldValue;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $newValue;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Actor $actor;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Ticket $ticket,
        string $field,
        ?string $oldValue,
        ?string $newValue,
        Actor $actor,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->ticket = $ticket;
        $this->field = $field;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->actor = $actor;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function getActor(): Actor
    {
        return $this->actor;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
