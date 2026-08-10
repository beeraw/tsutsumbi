<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Search\FullTextQuery;
use App\Search\TicketCriteria;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

use function is_array;
use function is_string;

use const DATE_ATOM;

/**
 * Query string of GET /api/tickets.
 *
 * Built by hand rather than through MapQueryString because "status" is
 * repeatable, and a repeated scalar and a comma-separated list are both natural
 * ways to write it — the two are accepted, and neither is a validation error.
 */
final readonly class ListTicketsQuery
{
    /**
     * @param list<string> $status
     */
    public function __construct(
        public ?string $project = null,
        #[Assert\All([new Assert\Choice(callback: [TicketStatus::class, 'values'], message: 'ticket.status.invalid')])]
        public array $status = [],
        #[Assert\Choice(callback: [TicketType::class, 'values'], message: 'ticket.type.invalid')]
        public ?string $type = null,
        #[Assert\Choice(callback: [TicketResolution::class, 'values'], message: 'ticket.resolution.invalid')]
        public ?string $resolution = null,
        public ?string $q = null,
        #[Assert\DateTime(format: DATE_ATOM, message: 'ticket.updated_since.invalid')]
        public ?string $updatedSince = null,
        #[Assert\Positive(message: 'pagination.page.invalid')]
        public int $page = 1,
        #[Assert\Positive(message: 'pagination.per_page.invalid')]
        #[Assert\LessThanOrEqual(value: TicketCriteria::MAX_PER_PAGE, message: 'pagination.per_page.too_large')]
        public int $perPage = TicketCriteria::DEFAULT_PER_PAGE,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;

        return new self(
            self::nullableString($query->all()['project'] ?? null),
            self::statusList($query->all()['status'] ?? null),
            self::nullableString($query->all()['type'] ?? null),
            self::nullableString($query->all()['resolution'] ?? null),
            self::nullableString($query->all()['q'] ?? null),
            self::nullableString($query->all()['updatedSince'] ?? null),
            $query->getInt('page', 1),
            $query->getInt('perPage', TicketCriteria::DEFAULT_PER_PAGE),
        );
    }

    public function toCriteria(): TicketCriteria
    {
        $search = null === $this->q || '' === trim($this->q) ? null : FullTextQuery::fromRaw($this->q);

        return new TicketCriteria(
            null === $this->project ? null : mb_strtoupper($this->project),
            array_map(static fn (string $status): TicketStatus => TicketStatus::from($status), $this->status),
            null === $this->type ? null : TicketType::from($this->type),
            null === $this->resolution ? null : TicketResolution::from($this->resolution),
            $search,
            null === $this->updatedSince ? null : new DateTimeImmutable($this->updatedSince),
            $this->page,
            $this->perPage,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function statusList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $statuses = [];
        foreach ($value as $item) {
            $item = self::nullableString($item);

            if (null !== $item) {
                $statuses[] = $item;
            }
        }

        return array_values(array_unique($statuses));
    }
}
