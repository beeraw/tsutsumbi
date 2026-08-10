<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttachmentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A file kept alongside a ticket. An exception, not a reflex: it only exists
 * when the information would otherwise be lost for good.
 *
 * The stored filename is generated server-side and the file lives outside the
 * web root; originalFilename is kept for display only.
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'attachment')]
#[ORM\Index(name: 'idx_attachment_ticket', columns: ['ticket_id'])]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ticket::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    /**
     * Size in bytes, after compression.
     */
    #[ORM\Column]
    private int $size;

    /**
     * Path relative to the attachment storage root, never web-accessible.
     */
    #[ORM\Column(length: 255)]
    private string $storagePath;

    #[ORM\Column(length: 64)]
    private string $checksum;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Actor $uploadedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Ticket $ticket,
        string $originalFilename,
        string $mimeType,
        int $size,
        string $storagePath,
        string $checksum,
        Actor $uploadedBy,
    ) {
        $this->ticket = $ticket;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storagePath = $storagePath;
        $this->checksum = $checksum;
        $this->uploadedBy = $uploadedBy;
        $this->createdAt = new DateTimeImmutable();

        $ticket->addAttachment($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getUploadedBy(): Actor
    {
        return $this->uploadedBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
