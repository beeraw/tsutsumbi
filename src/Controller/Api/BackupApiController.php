<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Backup\BackupService;
use App\Domain\Backup\DatabaseDumper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

use function basename;
use function flush;

/**
 * Backup API pulled by an outside job, under the "backup" firewall: no token, no session, only the shared secret. It
 * comes as the X-Backup-Secret header or, for clients that cannot set one, as the "secret" query parameter (which
 * then shows in the access logs). A wrong or missing secret gets a 404, which says nothing about the API being there.
 */
#[Route('/api', name: 'api_backup_')]
final class BackupApiController extends AbstractController
{
    #[Route('/database/dump', name: 'database_dump', methods: ['GET'])]
    public function dump(Request $request, BackupService $service, DatabaseDumper $dumper): StreamedResponse
    {
        $this->denyUnlessAuthorized($request, $service);

        $compress = $request->query->getBoolean('compress');
        $response = new StreamedResponse(static function () use ($compress, $dumper): void {
            $dumper->stream($compress, static function (string $chunk): void {
                echo $chunk;
                flush();
            });
        });

        $response->headers->set('Content-Type', $compress ? 'application/gzip' : 'application/sql');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $dumper->fileName($compress),
        ));

        return $response;
    }

    #[Route('/media/files', name: 'media_files', methods: ['GET'])]
    public function mediaFiles(Request $request, BackupService $service): JsonResponse
    {
        $this->denyUnlessAuthorized($request, $service);

        return $this->json(['files' => $service->fileIndex($request->query->getInt('min'))]);
    }

    #[Route('/media/download', name: 'media_download', methods: ['GET'])]
    public function mediaDownload(Request $request, BackupService $service): BinaryFileResponse
    {
        $this->denyUnlessAuthorized($request, $service);

        $path = $service->resolve($request->query->getString('path'));
        if (null === $path) {
            throw $this->createNotFoundException();
        }

        return $this->file($path, basename($path));
    }

    private function denyUnlessAuthorized(Request $request, BackupService $service): void
    {
        $secret = $request->headers->get('X-Backup-Secret') ?? $request->query->getString('secret');

        if (!$service->isAuthorized($secret)) {
            throw $this->createNotFoundException();
        }
    }
}
