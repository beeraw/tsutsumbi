<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\TicketPresenter;
use App\Domain\ActorProvider;
use App\Domain\TicketWorkflow;
use App\Dto\CreateCommentRequest;
use App\Dto\ListTicketsQuery;
use App\Dto\UpdateTicketRequest;
use App\Entity\Comment;
use App\Entity\Ticket;
use App\Repository\TicketEventRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function sprintf;

#[Route('/api/tickets')]
final class TicketApiController extends AbstractController
{
    private const string KEY_PATTERN = '[A-Za-z0-9]{1,10}-[1-9][0-9]*';

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketEventRepository $events,
        private readonly TicketPresenter $presenter,
        private readonly ActorProvider $actors,
    ) {
    }

    #[Route('', name: 'api_tickets_list', methods: ['GET'])]
    public function list(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $query = ListTicketsQuery::fromRequest($request);
        $violations = $validator->validate($query);

        if ($violations->count() > 0) {
            throw new ValidationFailedException($query, $violations);
        }

        $page = $this->tickets->search($query->toCriteria());

        return $this->json([
            'tickets' => array_map(
                fn (Ticket $ticket): array => $this->presenter->summary($ticket),
                $page->items,
            ),
            'total' => $page->total,
            'page' => $page->page,
            'perPage' => $page->perPage,
            'pageCount' => $page->pageCount(),
        ]);
    }

    #[Route('/{key}', name: 'api_tickets_show', requirements: ['key' => self::KEY_PATTERN], methods: ['GET'])]
    public function show(string $key): JsonResponse
    {
        $ticket = $this->requireTicket($key);

        return $this->json($this->presenter->detail($ticket, $this->events->findForTicket($ticket)));
    }

    #[Route('/{key}', name: 'api_tickets_update', requirements: ['key' => self::KEY_PATTERN], methods: ['PATCH'])]
    #[IsGranted('tickets:write')]
    public function update(
        string $key,
        Request $request,
        #[MapRequestPayload]
        UpdateTicketRequest $payload,
        TicketWorkflow $workflow,
    ): JsonResponse {
        $ticket = $this->requireTicket($key);

        // The payload keys, not the DTO's nulls: {"description": null} clears the
        // description, while omitting the key leaves it untouched.
        /** @var list<string> $providedFields */
        $providedFields = array_map(strval(...), array_keys($request->getPayload()->all()));

        $workflow->apply($ticket, $this->actors->current(), $payload->toChange($providedFields));

        return $this->json($this->presenter->detail($ticket, $this->events->findForTicket($ticket)));
    }

    #[Route('/{key}/comments', name: 'api_tickets_add_comment', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    #[IsGranted('comments:write')]
    public function addComment(
        string $key,
        #[MapRequestPayload]
        CreateCommentRequest $payload,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $comment = new Comment($this->requireTicket($key), trim($payload->body), $this->actors->current());

        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->json($this->presenter->comment($comment), Response::HTTP_CREATED);
    }

    private function requireTicket(string $key): Ticket
    {
        $ticket = $this->tickets->findOneByKey($key);

        if (null === $ticket) {
            throw $this->createNotFoundException(sprintf('No ticket is registered under key "%s".', $key));
        }

        return $ticket;
    }
}
