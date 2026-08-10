<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Form\ApiTokenForm;
use App\Form\Model\ApiTokenData;
use App\Repository\ApiTokenRepository;
use App\Security\ApiTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Token management, reachable through a human session only. There is
 * deliberately no administration scope on the machine side: an agent cannot mint
 * itself new powers.
 */
#[Route('/tokens')]
final class ApiTokenController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_token_index', methods: ['GET', 'POST'])]
    public function index(Request $request, ApiTokenGenerator $generator): Response
    {
        $form = $this->createForm(ApiTokenForm::class, new ApiTokenData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $generated = $generator->generate();
            $user = $this->getUser();

            $this->entityManager->persist(new ApiToken(
                $data->trimmedLabel(),
                $data->trimmedAgentSlug(),
                $data->scopes,
                $generated->selector,
                $generated->verifierHash,
                $user instanceof User ? $user : null,
                $data->expiresAt,
            ));
            $this->entityManager->flush();

            // The only moment the plain token exists outside the generator. It
            // travels in the flash bag and is never stored anywhere readable.
            $this->addFlash('token', $generated->plain);
            $this->addFlash('success', 'api_token.flash.created');

            return $this->redirectToRoute('app_token_index');
        }

        return $this->render('api_token/index.html.twig', [
            'tokens' => $this->tokens->findBy([], ['createdAt' => 'DESC']),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{selector}/revoke', name: 'app_token_revoke', requirements: ['selector' => '[0-9A-Za-z]{12}'], methods: ['POST'])]
    public function revoke(string $selector, Request $request): Response
    {
        $token = $this->tokens->findOneBySelector($selector);

        if (null === $token) {
            throw $this->createNotFoundException('No token carries this selector.');
        }

        if (!$this->isCsrfTokenValid('revoke'.$selector, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $token->revoke();
        $this->entityManager->flush();

        $this->addFlash('success', 'api_token.flash.revoked');

        return $this->redirectToRoute('app_token_index');
    }
}
