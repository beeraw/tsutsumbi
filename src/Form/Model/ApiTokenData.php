<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Enum\ApiTokenScope;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class ApiTokenData
{
    #[Assert\NotBlank(message: 'api_token.label.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 120, maxMessage: 'api_token.label.too_long')]
    public ?string $label = null;

    #[Assert\NotBlank(message: 'api_token.agent_slug.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 60, maxMessage: 'api_token.agent_slug.too_long')]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'api_token.agent_slug.invalid')]
    public ?string $agentSlug = null;

    /**
     * @var list<ApiTokenScope>
     */
    #[Assert\Count(min: 1, minMessage: 'api_token.scopes.not_empty')]
    public array $scopes = [];

    #[Assert\GreaterThan('today', message: 'api_token.expires_at.past')]
    public ?DateTimeImmutable $expiresAt = null;

    public function trimmedLabel(): string
    {
        return trim($this->label ?? '');
    }

    public function trimmedAgentSlug(): string
    {
        return trim($this->agentSlug ?? '');
    }
}
