<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActorRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function count;
use function mb_strtoupper;
use function mb_substr;
use function preg_split;
use function strstr;

/**
 * Anything that can act on a ticket: a human or an agent.
 *
 * Single-table inheritance keeps every authorship column a plain, non-nullable
 * foreign key, instead of the pair of nullable columns a two-table model forces
 * onto Ticket, Comment, TicketEvent and Attachment alike.
 */
#[ORM\Entity(repositoryClass: ActorRepository::class)]
#[ORM\Table(name: 'actor')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: Types::STRING, length: 10)]
#[ORM\DiscriminatorMap(['human' => User::class, 'agent' => Agent::class])]
abstract class Actor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Human-readable identity, used wherever an author is displayed.
     */
    abstract public function getDisplayName(): string;

    /**
     * Hex colour used to tell actors apart in the interface.
     */
    abstract public function getColor(): string;

    abstract public function isAgent(): bool;

    /**
     * One or two letters standing in for the actor in a dense list, where the
     * full name would cost a column of its own.
     *
     * It is the third way the interface tells actors apart, after the fill —
     * solid for an agent, outlined for a human — and the colour. Three signals
     * because the requirement is that the distinction survives a monochrome
     * screen and an eye that does not separate hues.
     */
    public function getMonogram(): string
    {
        $name = $this->getDisplayName();

        // A human is identified by an e-mail address; only the local part
        // carries a name, and "@" would otherwise win every time.
        $local = strstr($name, '@', true);

        if (false !== $local && '' !== $local) {
            $name = $local;
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($words[0] ?? '?', 0, 2));
    }
}
