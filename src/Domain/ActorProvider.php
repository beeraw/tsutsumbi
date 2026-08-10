<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Actor;
use App\Security\ApiActor;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who is acting right now: the logged-in human, or the agent the presented
 * token speaks for.
 */
final class ActorProvider
{
    private ?Actor $cached = null;

    public function __construct(private readonly Security $security)
    {
    }

    public function currentOrNull(): ?Actor
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        $user = $this->security->getUser();

        if ($user instanceof Actor) {
            return $this->cached = $user;
        }

        if ($user instanceof ApiActor) {
            return $this->cached = $user->getAgent();
        }

        return null;
    }

    public function current(): Actor
    {
        $actor = $this->currentOrNull();

        if (null === $actor) {
            throw new LogicException('No actor is available in the current context.');
        }

        return $actor;
    }
}
