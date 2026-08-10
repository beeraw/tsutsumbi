<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Agent;
use App\Entity\ApiToken;
use App\Enum\ApiTokenScope;
use LogicException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated identity behind an API call: an agent, plus the token that
 * says what it is allowed to do.
 *
 * Scopes live on the token, not on the agent, so the two are kept together
 * rather than making Agent itself the security user.
 */
final readonly class ApiActor implements UserInterface
{
    public const string ROLE = 'ROLE_API';

    public function __construct(
        private Agent $agent,
        private ApiToken $token,
    ) {
    }

    public function getAgent(): Agent
    {
        return $this->agent;
    }

    public function getToken(): ApiToken
    {
        return $this->token;
    }

    public function hasScope(ApiTokenScope $scope): bool
    {
        return $this->token->hasScope($scope);
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [self::ROLE];
    }

    /**
     * The selector, not the agent slug: several tokens can act as the same
     * agent with different scopes, and the logs must tell them apart.
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $selector = $this->token->getSelector();

        return '' !== $selector
            ? $selector
            : throw new LogicException('An API token cannot exist without a selector.');
    }

    public function eraseCredentials(): void
    {
    }
}
