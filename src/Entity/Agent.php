<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AgentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function count;

/**
 * A non-human actor: claude-code, claude-design, claude-review….
 *
 * Agents are created on the fly when a token presents an unknown slug. This is
 * deliberate: an agent can take on a new role without any human intervention.
 */
#[ORM\Entity(repositoryClass: AgentRepository::class)]
class Agent extends Actor
{
    /**
     * Fallback palette, picked deterministically from the slug so that a
     * freshly created agent is immediately distinguishable from the others.
     *
     * @var list<string>
     */
    private const array PALETTE = [
        '#1f3a68',
        '#2f6f6b',
        '#7a4a86',
        '#8a5a2b',
        '#4a6b2f',
        '#8a3a4a',
        '#3a5f8a',
        '#6b4a7a',
    ];

    #[ORM\Column(length: 60, unique: true)]
    private string $slug;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSeenAt = null;

    public function __construct(string $slug, ?string $label = null, ?string $color = null)
    {
        parent::__construct();

        $this->slug = $slug;
        $this->label = $label ?? self::labelFromSlug($slug);
        $this->color = $color ?? self::colorFromSlug($slug);
    }

    /**
     * "claude-code" becomes "Claude Code" — a readable default for an agent
     * that showed up without declaring a label.
     */
    public static function labelFromSlug(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    public static function colorFromSlug(string $slug): string
    {
        $index = unpack('N', substr(hash('sha256', $slug, true), 0, 4));

        if (false === $index) {
            return self::PALETTE[0];
        }

        return self::PALETTE[$index[1] % count(self::PALETTE)];
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getLastSeenAt(): ?DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->lastSeenAt = $at;
    }

    public function getDisplayName(): string
    {
        return $this->label;
    }

    public function isAgent(): bool
    {
        return true;
    }
}
