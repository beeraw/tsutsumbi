<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Domain\ActorProvider;
use App\Entity\Actor;
use App\Entity\Ticket;
use App\Entity\TicketEvent;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

use function array_key_exists;
use function is_bool;
use function is_scalar;

use const DATE_ATOM;

/**
 * Writes the audit journal: one TicketEvent per changed field.
 *
 * It hangs off onFlush rather than being called from controllers, which is the
 * point — a status change made from the API, from the interface or from a
 * console command all leave the same trace, and none of them can forget to.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TicketAuditListener
{
    public function __construct(private ActorProvider $actors)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $eventMetadata = $entityManager->getClassMetadata(TicketEvent::class);
        $now = new DateTimeImmutable();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Ticket) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            $actor = $this->resolveActor($entity);

            foreach (Ticket::AUDITED_FIELDS as $field) {
                if (!array_key_exists($field, $changeSet)) {
                    continue;
                }

                $old = self::stringify($changeSet[$field][0]);
                $new = self::stringify($changeSet[$field][1]);

                if ($old === $new) {
                    continue;
                }

                $event = new TicketEvent($entity, $field, $old, $new, $actor, $now);

                $entityManager->persist($event);
                // Inserting during onFlush only reaches the database if its
                // changeset is computed by hand: the unit of work is already
                // past the point where it does that on its own.
                $unitOfWork->computeChangeSet($eventMetadata, $event);
            }
        }
    }

    /**
     * Outside a request — a console command, a fixture — there is no
     * authenticated actor. Falling back to the ticket's author keeps the journal
     * complete rather than letting a change slip through unattributed.
     */
    private function resolveActor(Ticket $ticket): Actor
    {
        return $this->actors->currentOrNull() ?? $ticket->getCreatedBy();
    }

    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => null,
        };
    }
}
