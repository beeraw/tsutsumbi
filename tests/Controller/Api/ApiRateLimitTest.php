<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Http\ProblemType;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;

final class ApiRateLimitTest extends ApiTestCase
{
    private const int LIMIT = 30;

    public function testATokenIsThrottledOnceItsWindowIsFull(): void
    {
        $token = $this->createToken();

        for ($i = 0; $i < self::LIMIT; ++$i) {
            $this->request('GET', '/api/projects', token: $token);
            self::assertResponseIsSuccessful(sprintf('Request %d should still be inside the window.', $i + 1));
        }

        $this->request('GET', '/api/projects', token: $token);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertResponseHasHeader('Retry-After');
        self::assertSame(ProblemType::RATE_LIMITED, $this->responsePayload()['type']);
    }

    /**
     * The window is keyed on the token, not on the caller's address: several
     * agents behind one machine must not eat each other's budget.
     */
    public function testAnotherTokenKeepsItsOwnBudget(): void
    {
        $exhausted = $this->createToken();
        $fresh = $this->createToken();

        for ($i = 0; $i <= self::LIMIT; ++$i) {
            $this->request('GET', '/api/projects', token: $exhausted);
        }

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);

        $this->request('GET', '/api/projects', token: $fresh);

        self::assertResponseIsSuccessful();
    }
}
