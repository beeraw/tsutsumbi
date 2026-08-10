<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Last line of defence on the status/resolution pair.
 *
 * Ticket::applyStatus() already refuses an inconsistent move, so nothing here
 * should ever fire. That is exactly why it exists: a future setter, a fixture or
 * a hand-written query that sidesteps the entity's own door still cannot leave
 * a closed ticket without a resolution in the database.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TicketInvariantListener
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->assert($entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->assert($entity);
        }
    }

    private function assert(object $entity): void
    {
        if (!$entity instanceof Ticket) {
            return;
        }

        Ticket::assertConsistent($entity->getStatus(), $entity->getResolution());
    }
}
