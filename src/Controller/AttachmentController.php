<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attachment\AttachmentStorage;
use App\Entity\Attachment;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Attachments live outside the web root, so reaching one always goes through
 * here — and through the firewall this route sits behind.
 */
#[Route('/attachments')]
final class AttachmentController extends AbstractController
{
    #[Route('/{id}', name: 'app_attachment_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(
        #[MapEntity(id: 'id')]
        Attachment $attachment,
        AttachmentStorage $storage,
    ): Response {
        $response = new Response($storage->read($attachment));

        $response->headers->set('Content-Type', $attachment->getMimeType());
        // Always an attachment, never inline: the stored bytes are not a
        // document this application wants a browser to render in its own origin.
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->downloadName($attachment),
            'attachment',
        ));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * The original name with the extension of what was actually stored: a PNG
     * that came out as WebP must not be handed back claiming to be a PNG.
     */
    private function downloadName(Attachment $attachment): string
    {
        $stored = pathinfo($attachment->getStoragePath(), PATHINFO_EXTENSION);
        $base = pathinfo($attachment->getOriginalFilename(), PATHINFO_FILENAME);

        return '' === $base ? 'attachment.'.$stored : $base.'.'.$stored;
    }
}
