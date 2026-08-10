<?php

declare(strict_types=1);

namespace App\Dto;

use App\Domain\TicketChange;
use App\Enum\TicketPriority;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every field is optional: a PATCH says what changes, not what the ticket is.
 *
 * Which keys the caller actually sent cannot be read off this object — null is
 * a legitimate value for description and resolution — so the controller passes
 * the payload keys alongside it.
 */
final readonly class UpdateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'ticket.title.not_blank', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 200, maxMessage: 'ticket.title.too_long')]
        public ?string $title = null,
        public ?string $description = null,
        #[Assert\Choice(callback: [TicketType::class, 'values'], message: 'ticket.type.invalid')]
        public ?string $type = null,
        #[Assert\Choice(callback: [TicketPriority::class, 'values'], message: 'ticket.priority.invalid')]
        public ?string $priority = null,
        #[Assert\Choice(callback: [TicketStatus::class, 'values'], message: 'ticket.status.invalid')]
        public ?string $status = null,
        #[Assert\Choice(callback: [TicketResolution::class, 'values'], message: 'ticket.resolution.invalid')]
        public ?string $resolution = null,
        #[Assert\Length(max: 20000, maxMessage: 'comment.body.too_long')]
        public ?string $note = null,
    ) {
    }

    /**
     * @param list<string> $providedFields the keys present in the request body
     */
    public function toChange(array $providedFields): TicketChange
    {
        return new TicketChange(
            $providedFields,
            $this->title,
            $this->description,
            null === $this->type ? null : TicketType::from($this->type),
            null === $this->priority ? null : TicketPriority::from($this->priority),
            null === $this->status ? null : TicketStatus::from($this->status),
            null === $this->resolution ? null : TicketResolution::from($this->resolution),
            $this->note,
        );
    }
}
