<?php

declare(strict_types=1);

namespace App\Search;

use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use DateTimeImmutable;

/**
 * Filters for a ticket listing. Deliberately status-agnostic by default: closed
 * tickets stay fully searchable, and it is the caller — an active view, or the
 * status filter of the API — that narrows things down.
 */
final readonly class TicketCriteria
{
    public const int DEFAULT_PER_PAGE = 25;
    public const int MAX_PER_PAGE = 100;

    /**
     * @param list<TicketStatus> $statuses
     */
    public function __construct(
        public ?string $projectKey = null,
        public array $statuses = [],
        public ?TicketType $type = null,
        public ?TicketResolution $resolution = null,
        public ?FullTextQuery $search = null,
        public ?DateTimeImmutable $updatedSince = null,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public TicketSort $sort = TicketSort::Updated,
        public SortDirection $direction = SortDirection::Desc,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
