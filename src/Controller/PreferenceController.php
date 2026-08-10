<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;
use function str_starts_with;

/**
 * The two display preferences, stored in a cookie and applied on the server.
 *
 * They are POST forms rather than a script toggling a class, because the
 * interface has to work with scripting off — and because a cookie read while
 * rendering means the page arrives in the right theme instead of flashing the
 * wrong one first. Nothing here is account state: a preference is about this
 * browser, and losing it costs one click.
 *
 * With scripting on, the rail is folded on the spot and this endpoint is called
 * in the background for the storing alone; it then answers 204, since the page
 * the redirect points at is the one already on screen.
 */
#[Route('/preferences')]
final class PreferenceController extends AbstractController
{
    public const string THEME_COOKIE = 'theme';
    public const string RAIL_COOKIE = 'rail';

    private const int LIFETIME = 31_536_000; // one year

    /**
     * @var list<string>
     */
    private const array THEMES = ['light', 'dark'];

    /**
     * @var list<string>
     */
    private const array RAIL_STATES = ['wide', 'narrow'];

    #[Route('/theme', name: 'app_preference_theme', methods: ['POST'])]
    public function theme(Request $request): Response
    {
        return $this->store($request, self::THEME_COOKIE, self::THEMES);
    }

    #[Route('/rail', name: 'app_preference_rail', methods: ['POST'])]
    public function rail(Request $request): Response
    {
        return $this->store($request, self::RAIL_COOKIE, self::RAIL_STATES);
    }

    /**
     * @param list<string> $allowed
     */
    private function store(Request $request, string $name, array $allowed): Response
    {
        $this->assertCsrf($request, $name);

        $value = (string) $request->request->get('value');

        if (!in_array($value, $allowed, true)) {
            throw $this->createAccessDeniedException('Unknown preference value.');
        }

        /* Applied on the client and stored here: there is no page to come back
           to, and following the redirect would download one for nothing. */
        $response = $request->isXmlHttpRequest()
            ? new Response(null, Response::HTTP_NO_CONTENT)
            : $this->redirect($this->safeReturnPath($request));

        $response->headers->setCookie(Cookie::create(
            name: $name,
            value: $value,
            expire: time() + self::LIFETIME,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }

    /**
     * The form carries the page to come back to, so the toggle leaves the reader
     * where they were. A submitted path is user input: anything that is not a
     * plain absolute path — a scheme, a host, a protocol-relative "//" — is
     * dropped for the dashboard rather than followed.
     */
    private function safeReturnPath(Request $request): string
    {
        $target = (string) $request->request->get('from');

        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            return $target;
        }

        return $this->generateUrl('app_dashboard');
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
