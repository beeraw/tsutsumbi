<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Domain\TicketChange;
use App\Entity\Ticket;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class TicketStatusData
{
    #[Assert\NotNull(message: 'ticket.status.invalid')]
    public ?TicketStatus $status = null;

    public ?TicketResolution $resolution = null;

    #[Assert\Length(max: 20000, maxMessage: 'comment.body.too_long')]
    public ?string $note = null;

    public static function fromTicket(Ticket $ticket): self
    {
        $data = new self();
        $data->status = $ticket->getStatus();
        $data->resolution = $ticket->getResolution();

        return $data;
    }

    /**
     * The three fields are always declared as provided: the form submits them
     * every time, so an emptied resolution really does mean "clear it".
     */
    public function toChange(): TicketChange
    {
        return new TicketChange(
            [TicketChange::STATUS, TicketChange::RESOLUTION, TicketChange::NOTE],
            status: $this->status,
            resolution: $this->resolution,
            note: $this->note,
        );
    }
}
