<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ProjectPresenter;
use App\Api\TicketPresenter;
use App\Domain\ActorProvider;
use App\Domain\ProjectEditor;
use App\Domain\ProjectResolver;
use App\Domain\TicketFactory;
use App\Dto\CreateTicketRequest;
use App\Dto\ResolveProjectRequest;
use App\Dto\UpdateProjectRequest;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function sprintf;

#[Route('/api/projects')]
final class ProjectApiController extends AbstractController
{
    private const string KEY_PATTERN = '[A-Za-z0-9]{1,10}';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly TicketRepository $tickets,
        private readonly ProjectPresenter $projectPresenter,
        private readonly TicketPresenter $ticketPresenter,
        private readonly ActorProvider $actors,
    ) {
    }

    /**
     * The pivot every session starts with. Idempotent: the same path always
     * yields the same project, created only the first time.
     */
    #[Route('/resolve', name: 'api_projects_resolve', methods: ['POST'])]
    #[IsGranted('projects:write')]
    public function resolve(
        #[MapRequestPayload]
        ResolveProjectRequest $payload,
        ProjectResolver $resolver,
    ): JsonResponse {
        $resolution = $resolver->resolve($payload->path, $payload->name);

        return $this->json($this->projectPresenter->resolved(
            $resolution->project,
            $resolution->created,
            $this->tickets->findActiveByProject($resolution->project),
        ));
    }

    #[Route('', name: 'api_projects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $projects = array_map(
            fn (array $row): array => $this->projectPresenter->summary(
                $row['project'],
                $row['activeTickets'],
                $row['lastActivityAt'],
            ),
            $this->projects->findAllWithActivity(includeArchived: true),
        );

        return $this->json(['projects' => $projects]);
    }

    #[Route('/{key}', name: 'api_projects_show', requirements: ['key' => self::KEY_PATTERN], methods: ['GET'])]
    public function show(string $key): JsonResponse
    {
        $project = $this->requireProject($key);

        return $this->json($this->projectPresenter->summary(
            $project,
            $this->tickets->countActiveByProject($project),
        ) + [
            'activeTicketList' => array_map(
                fn ($ticket): array => $this->ticketPresenter->brief($ticket),
                $this->tickets->findActiveByProject($project),
            ),
        ]);
    }

    /**
     * What resolve() deliberately will not do. Resolution is idempotent by
     * contract — an agent replays it at every session start — so a change of
     * name, path or key is asked for here, by key, and only ever on purpose.
     */
    #[Route('/{key}', name: 'api_projects_update', requirements: ['key' => self::KEY_PATTERN], methods: ['PATCH'])]
    #[IsGranted('projects:write')]
    public function update(
        string $key,
        Request $request,
        #[MapRequestPayload]
        UpdateProjectRequest $payload,
        ProjectEditor $editor,
    ): JsonResponse {
        $project = $this->requireProject($key);

        // The payload keys, not the DTO's nulls: {"description": null} clears
        // the description, while omitting the key leaves it untouched.
        /** @var list<string> $providedFields */
        $providedFields = array_map(strval(...), array_keys($request->getPayload()->all()));

        $editor->apply($project, $payload->toChange($providedFields));

        return $this->json($this->projectPresenter->summary(
            $project,
            $this->tickets->countActiveByProject($project),
        ));
    }

    #[Route('/{key}/tickets', name: 'api_projects_create_ticket', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    #[IsGranted('tickets:write')]
    public function createTicket(
        string $key,
        #[MapRequestPayload]
        CreateTicketRequest $payload,
        TicketFactory $factory,
    ): JsonResponse {
        $ticket = $factory->create(
            $this->requireProject($key),
            $payload->title,
            $payload->type(),
            $this->actors->current(),
            $payload->description,
            $payload->priority(),
        );

        return $this->json(
            $this->ticketPresenter->detail($ticket, []),
            Response::HTTP_CREATED,
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
}
