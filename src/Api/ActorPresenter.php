<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Actor;
use App\Entity\Agent;
use App\Entity\User;

/**
 * The interface has to tell a human action from an agent one, and one agent
 * from another, so every actor is exposed with its kind and its colour.
 */
final readonly class ActorPresenter
{
    /**
     * @return array{type: string, name: string, color: string, slug: string|null}
     */
    public function present(Actor $actor): array
    {
        return [
            'type' => $actor->isAgent() ? 'agent' : 'human',
            'name' => $actor->getDisplayName(),
            'color' => $actor->getColor(),
            'slug' => $actor instanceof Agent ? $actor->getSlug() : null,
        ];
    }

    /**
     * @return array{type: string, name: string, color: string, slug: string|null, email: string|null}
     */
    public function presentDetailed(Actor $actor): array
    {
        return $this->present($actor) + [
            'email' => $actor instanceof User ? $actor->getEmail() : null,
        ];
    }
}
