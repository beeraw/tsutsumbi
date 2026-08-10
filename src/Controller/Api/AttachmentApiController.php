<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Attachment\AttachmentUploader;
use App\Domain\ActorProvider;
use App\Entity\Ticket;
use App\Exception\AttachmentRejectedException;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function sprintf;

use const DATE_ATOM;

#[Route('/api/tickets')]
final class AttachmentApiController extends AbstractController
{
    private const string KEY_PATTERN = '[A-Za-z0-9]{1,10}-[1-9][0-9]*';

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly ActorProvider $actors,
    ) {
    }

    /**
     * Multipart rather than JSON, because a base64 body would put the whole file
     * in memory twice for no benefit.
     */
    #[Route('/{key}/attachments', name: 'api_tickets_attach', requirements: ['key' => self::KEY_PATTERN], methods: ['POST'])]
    #[IsGranted('attachments:write')]
    public function attach(string $key, Request $request, AttachmentUploader $uploader): JsonResponse
    {
        $ticket = $this->requireTicket($key);
        $file = $request->files->get('file');
        $reason = trim((string) $request->request->get('reason', ''));

        if (!$file instanceof UploadedFile) {
            throw AttachmentRejectedException::missingFile();
        }

        if ('' === $reason) {
            // The reason is what makes the exception reviewable later. Without
            // it there is no way to tell a justified attachment from a reflex.
            throw AttachmentRejectedException::missingReason();
        }

        $attachment = $uploader->upload($ticket, $file, $this->actors->current(), $reason);

        return $this->json([
            'id' => $attachment->getId(),
            'ticket' => $ticket->getKey(),
            'originalFilename' => $attachment->getOriginalFilename(),
            'mimeType' => $attachment->getMimeType(),
            'size' => $attachment->getSize(),
            'checksum' => $attachment->getChecksum(),
            'createdAt' => $attachment->getCreatedAt()->format(DATE_ATOM),
        ], Response::HTTP_CREATED);
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
