<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A free-form note on a ticket, written by a human or an agent.
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'ticket_comment')]
#[ORM\Index(name: 'idx_comment_ticket', columns: ['ticket_id'])]
#[ORM\Index(name: 'ft_comment_body', columns: ['body'], flags: ['fulltext'])]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ticket::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Actor $author;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $editedAt = null;

    public function __construct(Ticket $ticket, string $body, Actor $author)
    {
        $this->ticket = $ticket;
        $this->body = $body;
        $this->author = $author;
        $this->createdAt = new DateTimeImmutable();

        $ticket->addComment($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function edit(string $body): void
    {
        $this->body = $body;
        $this->editedAt = new DateTimeImmutable();
    }

    public function getAuthor(): Actor
    {
        return $this->author;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }
}
