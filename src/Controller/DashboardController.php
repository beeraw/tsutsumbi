<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projects): Response
    {
        $withArchived = $request->query->getBoolean('archived');

        return $this->render('dashboard/index.html.twig', [
            'rows' => $projects->findAllWithActivity(includeArchived: $withArchived),
            'withArchived' => $withArchived,
        ]);
    }
}
