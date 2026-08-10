<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enum\TicketPriority;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;

use function in_array;

/**
 * What a PATCH asks to change on a ticket.
 *
 * The list of provided fields is carried explicitly because null is a real
 * value here: clearing a description and leaving it alone are different
 * intentions, and only the caller's payload tells them apart.
 */
final readonly class TicketChange
{
    public const string TITLE = 'title';
    public const string DESCRIPTION = 'description';
    public const string TYPE = 'type';
    public const string PRIORITY = 'priority';
    public const string STATUS = 'status';
    public const string RESOLUTION = 'resolution';
    public const string NOTE = 'note';

    /**
     * @param list<string> $provided
     */
    public function __construct(
        public array $provided,
        public ?string $title = null,
        public ?string $description = null,
        public ?TicketType $type = null,
        public ?TicketPriority $priority = null,
        public ?TicketStatus $status = null,
        public ?TicketResolution $resolution = null,
        public ?string $note = null,
    ) {
    }

    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->provided;
    }

    public function trimmedNote(): string
    {
        return trim($this->note ?? '');
    }
}
