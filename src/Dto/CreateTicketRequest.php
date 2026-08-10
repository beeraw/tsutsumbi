<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TicketPriority;
use App\Enum\TicketType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'ticket.title.not_blank', normalizer: 'trim')]
        #[Assert\Length(max: 200, maxMessage: 'ticket.title.too_long')]
        public string $title = '',
        #[Assert\Choice(callback: [TicketType::class, 'values'], message: 'ticket.type.invalid')]
        #[Assert\NotBlank(message: 'ticket.type.not_blank', normalizer: 'trim')]
        public string $type = '',
        public ?string $description = null,
        #[Assert\Choice(callback: [TicketPriority::class, 'values'], message: 'ticket.priority.invalid')]
        public ?string $priority = null,
    ) {
    }

    public function type(): TicketType
    {
        return TicketType::from($this->type);
    }

    public function priority(): ?TicketPriority
    {
        return null === $this->priority ? null : TicketPriority::from($this->priority);
    }
}
