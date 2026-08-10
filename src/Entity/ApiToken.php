<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ApiTokenScope;
use App\Repository\ApiTokenRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function in_array;

/**
 * An opaque machine token, shaped as tsu_{selector}_{verifier}.
 *
 * The selector is stored in the clear and indexed, so a lookup costs one query.
 * The verifier is stored hashed and compared in constant time. The full token
 * is shown once, at creation, and never again.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_token')]
#[ORM\UniqueConstraint(name: 'uniq_api_token_selector', columns: ['selector'])]
class ApiToken
{
    public const string PREFIX = 'tsu';
    public const int SELECTOR_LENGTH = 12;
    public const int VERIFIER_LENGTH = 43;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $label;

    /**
     * Slug of the agent this token acts as. An unknown slug creates the agent.
     */
    #[ORM\Column(length: 60)]
    private string $agentSlug;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $scopes;

    #[ORM\Column(length: self::SELECTOR_LENGTH)]
    private string $selector;

    #[ORM\Column(length: 255)]
    private string $verifierHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    /**
     * @param list<ApiTokenScope> $scopes
     */
    public function __construct(
        string $label,
        string $agentSlug,
        array $scopes,
        string $selector,
        string $verifierHash,
        ?User $createdBy = null,
        ?DateTimeImmutable $expiresAt = null,
    ) {
        $this->label = $label;
        $this->agentSlug = $agentSlug;
        $this->scopes = array_values(array_unique(array_map(
            static fn (ApiTokenScope $scope): string => $scope->value,
            $scopes,
        )));
        $this->selector = $selector;
        $this->verifierHash = $verifierHash;
        $this->createdBy = $createdBy;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getAgentSlug(): string
    {
        return $this->agentSlug;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(ApiTokenScope $scope): bool
    {
        return in_array($scope->value, $this->scopes, true);
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getVerifierHash(): string
    {
        return $this->verifierHash;
    }

    public function setVerifierHash(string $verifierHash): void
    {
        $this->verifierHash = $verifierHash;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->lastUsedAt = $at;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?DateTimeImmutable $at = null): void
    {
        $this->revokedAt ??= $at ?? new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function isUsable(?DateTimeImmutable $now = null): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }
}
