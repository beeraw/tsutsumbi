<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\TicketStatus;
use DomainException;

use function sprintf;

/**
 * Raised when a ticket would be left in a state the model forbids.
 *
 * It carries both forms of the same refusal: a technical message, which the API
 * puts in the problem detail because a machine client is better served by a
 * stable string than a localised one, and a translation key, which the interface
 * turns into a form error.
 */
final class InvalidTicketStateException extends DomainException
{
    /**
     * @param array<string, string> $translationParameters
     */
    private function __construct(
        string $message,
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
    ) {
        parent::__construct($message);
    }

    public static function resolutionRequired(): self
    {
        return new self(
            'A closed ticket must carry a resolution.',
            'ticket.state.resolution_required',
        );
    }

    public static function resolutionForbidden(TicketStatus $status): self
    {
        return new self(
            sprintf('A ticket in status "%s" must not carry a resolution.', $status->value),
            'ticket.state.resolution_forbidden',
        );
    }

    public static function noteRequired(TicketStatus $status): self
    {
        return new self(
            sprintf('Moving a ticket to status "%s" requires an explanatory note.', $status->value),
            'ticket.state.note_required',
        );
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }
}
