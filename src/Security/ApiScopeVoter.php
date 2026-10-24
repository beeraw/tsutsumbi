<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\ApiTokenScope;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants scope attributes — 'tickets:write' and friends — from the presented
 * token rather than from roles, because two tokens acting as the same agent may
 * legitimately be allowed different things.
 *
 * @extends Voter<string, mixed>
 */
final class ApiScopeVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== ApiTokenScope::tryFrom($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $scope = ApiTokenScope::tryFrom($attribute);

        if (!$user instanceof ApiActor || null === $scope) {
            return false;
        }

        return $user->hasScope($scope);
    }
}
