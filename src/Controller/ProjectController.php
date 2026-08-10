<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use App\Search\FullTextQuery;
use App\Search\SortDirection;
use App\Search\TicketCriteria;
use App\Search\TicketSort;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;
use function sprintf;

#[Route('/projects')]
final class ProjectController extends AbstractController
{
    private const string KEY_PATTERN = '[A-Za-z0-9]{1,10}';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly TicketRepository $tickets,
    ) {
    }

    #[Route('/{key}', name: 'app_project_show', requirements: ['key' => self::KEY_PATTERN], methods: ['GET'])]
    public function show(string $key, Request $request): Response
    {
        $project = $this->requireProject($key);
        $showClosed = $request->query->getBoolean('closed');
        $criteria = $this->criteriaFromRequest($project, $request, $showClosed);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'page' => $this->tickets->search($criteria),
            'criteria' => $criteria,
            'showClosed' => $showClosed,
            'search' => $request->query->get('q', ''),
            // Keyed by value so the head can name its columns: sorts.key,
            // sorts.title, and so on.
            'sorts' => array_combine(
                array_map(static fn (TicketSort $sort): string => $sort->value, TicketSort::cases()),
                TicketSort::cases(),
            ),
            'types' => TicketType::cases(),
            'statuses' => TicketStatus::cases(),
            'resolutions' => TicketResolution::cases(),
        ]);
    }

    #[Route('/{key}/archive', name: 'app_project_archive', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    public function archive(string $key, Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = $this->requireProject($key);
        $this->assertCsrf($request, 'archive'.$project->getKey());

        $project->isArchived() ? $project->unarchive() : $project->archive();
        $entityManager->flush();

        $this->addFlash(
            'success',
            $project->isArchived() ? 'project.flash.archived' : 'project.flash.unarchived',
        );

        return $this->redirectToRoute('app_project_show', ['key' => $project->getKey()]);
    }

    /**
     * Unlike the API, a filter value the interface does not recognise is dropped
     * rather than refused: it can only come from a hand-edited URL, and a listing
     * that silently ignores nonsense beats one that shows an error page.
     */
    private function criteriaFromRequest(Project $project, Request $request, bool $showClosed): TicketCriteria
    {
        $status = TicketStatus::tryFrom((string) $request->query->get('status', ''));
        $statuses = match (true) {
            null !== $status => [$status],
            // "Show closed" off means the active views of the specification: the
            // closed tickets are still there, they are simply not in the way.
            !$showClosed => array_map(
                static fn (string $value): TicketStatus => TicketStatus::from($value),
                TicketStatus::activeValues(),
            ),
            default => [],
        };

        $rawSearch = $request->query->get('q');
        $search = is_string($rawSearch) && '' !== trim($rawSearch) ? FullTextQuery::fromRaw($rawSearch) : null;

        // An unknown column falls back to the default listing, and a column
        // asked for without a direction gets the one a first click would give:
        // "?sort=title" is a link somebody may well write by hand, and it
        // should mean the obvious thing.
        $sort = TicketSort::tryFrom((string) $request->query->get('sort', '')) ?? TicketSort::default();
        $direction = SortDirection::tryFrom((string) $request->query->get('dir', '')) ?? $sort->initialDirection();

        return new TicketCriteria(
            projectKey: $project->getKey(),
            statuses: $statuses,
            type: TicketType::tryFrom((string) $request->query->get('type', '')),
            resolution: TicketResolution::tryFrom((string) $request->query->get('resolution', '')),
            search: $search,
            page: max(1, $request->query->getInt('page', 1)),
            perPage: TicketCriteria::DEFAULT_PER_PAGE,
            sort: $sort,
            direction: $direction,
        );
    }

    private function requireProject(string $key): Project
    {
        $project = $this->projects->findOneByKey(mb_strtoupper($key));

        if (null === $project) {
            throw $this->createNotFoundException(sprintf('No project is registered under key "%s".', $key));
        }

        return $project;
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
