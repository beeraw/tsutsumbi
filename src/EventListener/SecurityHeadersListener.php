<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\ContentSecurityPolicy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Transport-level hardening on every response.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SecurityHeadersListener
{
    public function __construct(
        private ContentSecurityPolicy $policy,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // The API answers JSON to a non-browser client, where a policy about
        // scripts and stylesheets has nothing to act on.
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            $headers->set('Content-Security-Policy', $this->policy->build($this->debug));
        }
    }
}
