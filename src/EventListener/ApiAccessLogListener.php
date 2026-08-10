<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiActor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records every authenticated API call: which token, which agent, which route,
 * which status, from where.
 *
 * The selector is logged, never the verifier — the log must not be a place a
 * usable token can be recovered from.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ApiAccessLogListener
{
    public function __construct(
        private Security $security,
        #[Autowire(service: 'monolog.logger.api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof ApiActor) {
            return;
        }

        $request = $event->getRequest();

        $this->logger->info('API call', [
            'selector' => $user->getToken()->getSelector(),
            'agent' => $user->getAgent()->getSlug(),
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $event->getResponse()->getStatusCode(),
            'ip' => $request->getClientIp(),
        ]);
    }
}
