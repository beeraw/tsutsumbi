<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiToken;
use App\Http\ProblemJsonResponseFactory;
use App\Http\ProblemType;
use App\Repository\ApiTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

use function strlen;

/**
 * Authenticates a machine call from an opaque bearer token.
 *
 * No JWT here on purpose: there is a single non-interactive client, so a signed
 * self-describing token would only add a format on top of a secret that lives in
 * the environment either way — and it could not be revoked.
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string SCHEME = 'Bearer ';

    /**
     * Compared against when no token matches the selector, so a wrong selector
     * and a wrong verifier take the same time to reject.
     */
    private const string DUMMY_HASH = '$2y$13$abcdefghijklmnopqrstuuAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly ApiTokenGenerator $generator,
        private readonly AgentRegistrar $agents,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProblemJsonResponseFactory $problems,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $parsed = $this->generator->parse($this->extractCredentials($request));

        if (null === $parsed) {
            throw new CustomUserMessageAuthenticationException('api.error.token.malformed');
        }

        $token = $this->tokens->findOneBySelector($parsed->selector);

        if (!$this->verify($token, $parsed)) {
            throw new CustomUserMessageAuthenticationException('api.error.token.invalid');
        }

        $now = new DateTimeImmutable();

        if ($token->isRevoked()) {
            throw new CustomUserMessageAuthenticationException('api.error.token.revoked');
        }

        if ($token->isExpired($now)) {
            throw new CustomUserMessageAuthenticationException('api.error.token.expired');
        }

        $agent = $this->agents->findOrCreate($token->getAgentSlug());

        $token->touch($now);
        $agent->touch($now);
        $this->entityManager->flush();

        $actor = new ApiActor($agent, $token);

        return new SelfValidatingPassport(
            new UserBadge($actor->getUserIdentifier(), static fn (): ApiActor => $actor),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->problems->create(
            Response::HTTP_UNAUTHORIZED,
            ProblemType::UNAUTHORIZED,
            'api.error.unauthorized',
            detailKey: $exception instanceof CustomUserMessageAuthenticationException
                ? $exception->getMessageKey()
                : null,
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->problems->create(
            Response::HTTP_UNAUTHORIZED,
            ProblemType::UNAUTHORIZED,
            'api.error.unauthorized',
            detailKey: 'api.error.token.missing',
            headers: ['WWW-Authenticate' => 'Bearer realm="tsutsumbi"'],
        );
    }

    private function extractCredentials(Request $request): string
    {
        $header = (string) $request->headers->get('Authorization', '');

        if (!str_starts_with($header, self::SCHEME)) {
            throw new CustomUserMessageAuthenticationException('api.error.token.scheme');
        }

        return substr($header, strlen(self::SCHEME));
    }

    /**
     * @phpstan-assert-if-true !null $token
     */
    private function verify(?ApiToken $token, ParsedApiToken $parsed): bool
    {
        if (null === $token) {
            password_verify($parsed->verifier, self::DUMMY_HASH);

            return false;
        }

        // The selector is compared again in PHP even though the database already
        // matched it: a case-insensitive or padding-insensitive collation would
        // otherwise hand back a neighbouring row.
        if (!hash_equals($token->getSelector(), $parsed->selector)) {
            return false;
        }

        return password_verify($parsed->verifier, $token->getVerifierHash());
    }
}
