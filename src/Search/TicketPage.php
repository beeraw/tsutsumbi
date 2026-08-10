<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Ticket;

final readonly class TicketPage
{
    /**
     * @param list<Ticket> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
