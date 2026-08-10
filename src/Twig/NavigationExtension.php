<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ProjectRepository;
use DateTimeImmutable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The short list of projects the rail carries on every screen.
 *
 * It exists for one reason: a blocked ticket has to be visible from wherever
 * the reader happens to be, not only from the dashboard. The list is the most
 * recently active projects — the model has no notion of a pinned project, and
 * inventing one to fill a slot in the design would be a feature, not a layout.
 */
final class NavigationExtension extends AbstractExtension
{
    private const int LIMIT = 6;

    public function __construct(private readonly ProjectRepository $projects)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_projects', $this->recentProjects(...)),
        ];
    }

    /**
     * @return list<array{project: \App\Entity\Project, activeTickets: int, blockedTickets: int, lastActivityAt: DateTimeImmutable}>
     */
    public function recentProjects(): array
    {
        return $this->projects->findAllWithActivity(limit: self::LIMIT);
    }
}
