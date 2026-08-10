<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A human actor, authenticated through the session firewall.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends Actor implements UserInterface, PasswordAuthenticatedUserInterface
{
    private const string COLOR = '#1b2a4a';

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    private string $password = '';

    /**
     * Reserved for the optional TOTP second factor. Kept in the model from the
     * start so enabling it later needs no migration on a populated table.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    public function __construct(string $email)
    {
        parent::__construct();

        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return '' !== $this->email
            ? $this->email
            : throw new LogicException('A user cannot exist without an email.');
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?DateTimeImmutable $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
    }

    public function eraseCredentials(): void
    {
    }

    public function getDisplayName(): string
    {
        return $this->email;
    }

    public function getColor(): string
    {
        return self::COLOR;
    }

    public function isAgent(): bool
    {
        return false;
    }
}
