<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\TicketEvent;
use App\Enum\TicketPriority;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Turns an audit row into something a human reads.
 *
 * A description rewrite is reported as a rewrite rather than printed: the
 * journal keeps both versions in full, but a timeline that dumps two paragraphs
 * per edit stops being a timeline.
 */
final class TicketEventExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('event_field_key', $this->fieldKey(...)),
            new TwigFunction('event_value_key', $this->valueKey(...)),
            new TwigFunction('event_is_prose', $this->isProse(...)),
        ];
    }

    public function fieldKey(TicketEvent $event): string
    {
        return 'ticket.field.'.$event->getField();
    }

    /**
     * @return string|null a translation key for an enum value, the raw text
     *                     otherwise, null when the field was emptied
     */
    public function valueKey(TicketEvent $event, ?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return match ($event->getField()) {
            'status' => TicketStatus::tryFrom($value)?->translationKey() ?? $value,
            'resolution' => TicketResolution::tryFrom($value)?->translationKey() ?? $value,
            'type' => TicketType::tryFrom($value)?->translationKey() ?? $value,
            'priority' => TicketPriority::tryFrom($value)?->translationKey() ?? $value,
            default => $value,
        };
    }

    /**
     * True for the fields whose values are too long to show inline.
     */
    public function isProse(TicketEvent $event): bool
    {
        return 'description' === $event->getField();
    }
}
