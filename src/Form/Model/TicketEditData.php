<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Domain\TicketChange;
use App\Entity\Ticket;
use App\Enum\TicketPriority;
use App\Enum\TicketType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mutable on purpose: this is what the form writes into. The readonly DTOs of the
 * API describe a request that already arrived; this one is being filled in.
 */
final class TicketEditData
{
    #[Assert\NotBlank(message: 'ticket.title.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 200, maxMessage: 'ticket.title.too_long')]
    public ?string $title = null;

    public ?string $description = null;

    #[Assert\NotNull(message: 'ticket.type.not_blank')]
    public ?TicketType $type = null;

    #[Assert\NotNull(message: 'ticket.priority.invalid')]
    public ?TicketPriority $priority = null;

    public static function fromTicket(Ticket $ticket): self
    {
        $data = new self();
        $data->title = $ticket->getTitle();
        $data->description = $ticket->getDescription();
        $data->type = $ticket->getType();
        $data->priority = $ticket->getPriority();

        return $data;
    }

    public function toChange(): TicketChange
    {
        return new TicketChange(
            [TicketChange::TITLE, TicketChange::DESCRIPTION, TicketChange::TYPE, TicketChange::PRIORITY],
            title: $this->title,
            description: '' === trim($this->description ?? '') ? null : $this->description,
            type: $this->type,
            priority: $this->priority,
        );
    }
}
