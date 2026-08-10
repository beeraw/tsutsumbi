<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Attachment;
use App\Entity\Comment;
use App\Entity\Ticket;
use App\Entity\TicketEvent;

use const DATE_ATOM;

final readonly class TicketPresenter
{
    public function __construct(private ActorPresenter $actors)
    {
    }

    /**
     * The shape resolve() returns for active tickets: enough for an agent to
     * recognise work already under way, and nothing more.
     *
     * @return array{key: string, title: string, status: string, type: string}
     */
    public function brief(Ticket $ticket): array
    {
        return [
            'key' => $ticket->getKey(),
            'title' => $ticket->getTitle(),
            'status' => $ticket->getStatus()->value,
            'type' => $ticket->getType()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Ticket $ticket): array
    {
        return [
            'key' => $ticket->getKey(),
            'project' => $ticket->getProject()->getKey(),
            'title' => $ticket->getTitle(),
            'type' => $ticket->getType()->value,
            'status' => $ticket->getStatus()->value,
            'resolution' => $ticket->getResolution()?->value,
            'priority' => $ticket->getPriority()->value,
            'createdBy' => $this->actors->present($ticket->getCreatedBy()),
            'createdAt' => $ticket->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $ticket->getUpdatedAt()->format(DATE_ATOM),
            'startedAt' => $ticket->getStartedAt()?->format(DATE_ATOM),
            'closedAt' => $ticket->getClosedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<TicketEvent> $events
     *
     * @return array<string, mixed>
     */
    public function detail(Ticket $ticket, array $events): array
    {
        return $this->summary($ticket) + [
            'description' => $ticket->getDescription(),
            'comments' => array_map(
                fn (Comment $comment): array => $this->comment($comment),
                $ticket->getComments()->toArray(),
            ),
            'events' => array_map(
                fn (TicketEvent $event): array => $this->event($event),
                $events,
            ),
            'attachments' => array_map(
                fn (Attachment $attachment): array => $this->attachment($attachment),
                $ticket->getAttachments()->toArray(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->getId(),
            'originalFilename' => $attachment->getOriginalFilename(),
            'mimeType' => $attachment->getMimeType(),
            'size' => $attachment->getSize(),
            'checksum' => $attachment->getChecksum(),
            'uploadedBy' => $this->actors->present($attachment->getUploadedBy()),
            'createdAt' => $attachment->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function comment(Comment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'body' => $comment->getBody(),
            'author' => $this->actors->present($comment->getAuthor()),
            'createdAt' => $comment->getCreatedAt()->format(DATE_ATOM),
            'editedAt' => $comment->getEditedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function event(TicketEvent $event): array
    {
        return [
            'id' => $event->getId(),
            'field' => $event->getField(),
            'oldValue' => $event->getOldValue(),
            'newValue' => $event->getNewValue(),
            'actor' => $this->actors->present($event->getActor()),
            'createdAt' => $event->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
