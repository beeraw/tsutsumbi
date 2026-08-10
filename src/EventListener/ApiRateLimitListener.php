<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ApiActor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Throttles the API per token.
 *
 * Priority 7 puts this just after the firewall, which runs at 8: the token is
 * already identified, so the window is keyed on the token that is actually being
 * used rather than on an address several agents may share.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class ApiRateLimitListener
{
    public function __construct(
        private Security $security,
        #[Autowire(service: 'limiter.api')]
        private RateLimiterFactoryInterface $limiterFactory,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof ApiActor) {
            return;
        }

        $limit = $this->limiterFactory->create($user->getUserIdentifier())->consume();

        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

        throw new TooManyRequestsHttpException(max(1, $retryAfter));
    }
}
