<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Agent;
use App\Entity\ApiToken;
use App\Enum\ApiTokenScope;
use App\Http\ProblemJsonResponseFactory;
use App\Http\ProblemType;
use App\Repository\AgentRepository;
use App\Repository\ApiTokenRepository;
use App\Tests\ApiTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;

final class ApiAuthenticationTest extends ApiTestCase
{
    public function testAValidTokenGetsThrough(): void
    {
        $this->request('GET', '/api/projects', token: $this->createToken());

        self::assertResponseIsSuccessful();
    }

    public function testWithoutATokenTheAnswerIsAnUnauthorizedProblem(): void
    {
        $this->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('Content-Type', ProblemJsonResponseFactory::CONTENT_TYPE);
        self::assertResponseHasHeader('WWW-Authenticate');
        self::assertSame(ProblemType::UNAUTHORIZED, $this->responsePayload()['type']);
        // A translation key leaking into the payload would mean the message was
        // never wired up, so the assertion is on the text a reader would see.
        self::assertSame('No token was presented', $this->responsePayload()['detail']);
    }

    public function testABearerTokenThatIsNotShapedLikeOneIsRejected(): void
    {
        $this->request('GET', '/api/projects', token: 'not-a-token');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(ProblemType::UNAUTHORIZED, $this->responsePayload()['type']);
    }

    public function testAnotherAuthenticationSchemeIsRejected(): void
    {
        $this->client->request('GET', '/api/projects', server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('user:pass'),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAKnownSelectorWithTheWrongVerifierIsRejected(): void
    {
        $plain = $this->createToken();
        [$prefix, $selector] = explode('_', $plain);

        $this->request('GET', '/api/projects', token: sprintf('%s_%s_%s', $prefix, $selector, str_repeat('z', 43)));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testARevokedTokenIsRejected(): void
    {
        $this->request('GET', '/api/projects', token: $this->createToken(revoked: true));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('The token has been revoked', $this->responsePayload()['detail']);
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $expired = $this->createToken(expiresAt: new DateTimeImmutable('-1 hour'));

        $this->request('GET', '/api/projects', token: $expired);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('The token has expired', $this->responsePayload()['detail']);
    }

    public function testATokenExpiringLaterStillWorks(): void
    {
        $this->request('GET', '/api/projects', token: $this->createToken(expiresAt: new DateTimeImmutable('+1 hour')));

        self::assertResponseIsSuccessful();
    }

    public function testATokenWithoutTheRequiredScopeIsForbidden(): void
    {
        $token = $this->createToken([ApiTokenScope::CommentsWrite]);

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseHeaderSame('Content-Type', ProblemJsonResponseFactory::CONTENT_TYPE);
        self::assertSame(ProblemType::FORBIDDEN, $this->responsePayload()['type']);
    }

    public function testATokenWithTheRequiredScopeIsAllowedThrough(): void
    {
        $token = $this->createToken([ApiTokenScope::ProjectsWrite]);

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $token);

        self::assertResponseIsSuccessful();
    }

    /**
     * Agents are created on the fly, so an agent can take on a new role without
     * anybody provisioning it first.
     */
    public function testAnUnknownAgentSlugCreatesTheAgent(): void
    {
        $slug = 'claude-review-'.bin2hex(random_bytes(3));
        $agents = static::getContainer()->get(AgentRepository::class);

        self::assertNull($agents->findOneBySlug($slug), 'The slug must be unknown for this test to mean anything.');

        $this->request('GET', '/api/projects', token: $this->createToken(agentSlug: $slug));

        self::assertResponseIsSuccessful();

        $agent = $agents->findOneBySlug($slug);

        self::assertNotNull($agent);
        self::assertSame($slug, $agent->getSlug());
        self::assertNotNull($agent->getLastSeenAt());
    }

    public function testASlugBecomesAReadableLabelOnItsOwn(): void
    {
        self::assertSame('Claude Review', Agent::labelFromSlug('claude-review'));
    }

    public function testTwoAgentsGetDifferentColours(): void
    {
        self::assertNotSame(
            Agent::colorFromSlug('claude-code'),
            Agent::colorFromSlug('claude-design'),
        );
    }

    public function testUsingATokenRecordsWhenItWasLastSeen(): void
    {
        $plain = $this->createToken();
        [, $selector] = explode('_', $plain);

        $this->request('GET', '/api/projects', token: $plain);

        self::assertResponseIsSuccessful();

        $token = static::getContainer()->get(ApiTokenRepository::class)->findOneBySelector($selector);

        self::assertInstanceOf(ApiToken::class, $token);
        self::assertNotNull($token->getLastUsedAt());
    }
}
