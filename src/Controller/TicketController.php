<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ActorProvider;
use App\Domain\TicketWorkflow;
use App\Entity\Comment;
use App\Entity\Ticket;
use App\Exception\InvalidTicketStateException;
use App\Form\CommentForm;
use App\Form\Model\CommentData;
use App\Form\Model\TicketEditData;
use App\Form\Model\TicketStatusData;
use App\Form\TicketEditForm;
use App\Form\TicketStatusForm;
use App\Repository\TicketEventRepository;
use App\Repository\TicketRepository;
use App\Timeline\TicketTimelineBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function sprintf;

#[Route('/tickets')]
final class TicketController extends AbstractController
{
    private const string KEY_PATTERN = '[A-Za-z0-9]{1,10}-[1-9][0-9]*';

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketEventRepository $events,
        private readonly TicketTimelineBuilder $timeline,
        private readonly TicketWorkflow $workflow,
        private readonly ActorProvider $actors,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/{key}', name: 'app_ticket_show', requirements: ['key' => self::KEY_PATTERN], methods: ['GET'])]
    public function show(string $key): Response
    {
        return $this->renderTicket($this->requireTicket($key));
    }

    #[Route('/{key}/edit', name: 'app_ticket_edit', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    public function edit(string $key, Request $request): Response
    {
        $ticket = $this->requireTicket($key);
        $form = $this->editForm($ticket);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderTicket($ticket, editForm: $form, panel: 'edit');
        }

        $this->workflow->apply($ticket, $this->actors->current(), $form->getData()->toChange());
        $this->addFlash('success', 'ticket.flash.updated');

        return $this->redirectToRoute('app_ticket_show', ['key' => $ticket->getKey()]);
    }

    /**
     * Reopening is the same route as any other move: dropping the resolution is
     * what the domain does when the status leaves done, not a separate action.
     */
    #[Route('/{key}/status', name: 'app_ticket_status', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    public function changeStatus(string $key, Request $request): Response
    {
        $ticket = $this->requireTicket($key);
        $form = $this->statusForm($ticket);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderTicket($ticket, statusForm: $form, panel: 'status');
        }

        try {
            $this->workflow->apply($ticket, $this->actors->current(), $form->getData()->toChange());
        } catch (InvalidTicketStateException $exception) {
            // The domain owns the rule; the form only reports what it said.
            $form->addError(new FormError($this->translator->trans(
                $exception->getTranslationKey(),
                $exception->getTranslationParameters(),
                'validators',
            )));

            return $this->renderTicket($ticket, statusForm: $form, panel: 'status');
        }

        $this->addFlash('success', 'ticket.flash.status_changed');

        return $this->redirectToRoute('app_ticket_show', ['key' => $ticket->getKey()]);
    }

    #[Route('/{key}/comments', name: 'app_ticket_comment', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    public function comment(string $key, Request $request, EntityManagerInterface $entityManager): Response
    {
        $ticket = $this->requireTicket($key);
        $form = $this->commentForm($ticket);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderTicket($ticket, commentForm: $form);
        }

        $entityManager->persist(new Comment($ticket, $form->getData()->trimmedBody(), $this->actors->current()));
        $entityManager->flush();

        $this->addFlash('success', 'comment.flash.added');

        return $this->redirectToRoute('app_ticket_show', ['key' => $ticket->getKey()]);
    }

    /**
     * @param FormInterface<TicketEditData>|null   $editForm
     * @param FormInterface<TicketStatusData>|null $statusForm
     * @param FormInterface<CommentData>|null      $commentForm
     */
    private function renderTicket(
        Ticket $ticket,
        ?FormInterface $editForm = null,
        ?FormInterface $statusForm = null,
        ?FormInterface $commentForm = null,
        ?string $panel = null,
    ): Response {
        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'timeline' => $this->timeline->build($ticket, $this->events->findForTicket($ticket)),
            'editForm' => ($editForm ?? $this->editForm($ticket))->createView(),
            'statusForm' => ($statusForm ?? $this->statusForm($ticket))->createView(),
            'commentForm' => ($commentForm ?? $this->commentForm($ticket))->createView(),
            // Which panel to reopen when a submission came back with errors.
            'panel' => $panel,
        ]);
    }

    /**
     * @return FormInterface<TicketEditData>
     */
    private function editForm(Ticket $ticket): FormInterface
    {
        return $this->createForm(TicketEditForm::class, TicketEditData::fromTicket($ticket), [
            'action' => $this->generateUrl('app_ticket_edit', ['key' => $ticket->getKey()]),
        ]);
    }

    /**
     * @return FormInterface<TicketStatusData>
     */
    private function statusForm(Ticket $ticket): FormInterface
    {
        return $this->createForm(TicketStatusForm::class, TicketStatusData::fromTicket($ticket), [
            'action' => $this->generateUrl('app_ticket_status', ['key' => $ticket->getKey()]),
        ]);
    }

    /**
     * @return FormInterface<CommentData>
     */
    private function commentForm(Ticket $ticket): FormInterface
    {
        return $this->createForm(CommentForm::class, new CommentData(), [
            'action' => $this->generateUrl('app_ticket_comment', ['key' => $ticket->getKey()]),
        ]);
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
