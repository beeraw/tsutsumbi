<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Http\ProblemJsonResponseFactory;
use App\Http\ProblemType;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;

final class ProjectApiTest extends ApiTestCase
{
    public function testResolveCreatesTheProjectOnItsFirstCall(): void
    {
        $token = $this->createToken();
        $path = $this->uniquePath('maji');

        $this->request('POST', '/api/projects/resolve', ['path' => $path, 'name' => 'Maji'], $token);

        self::assertResponseIsSuccessful();

        $payload = $this->responsePayload();

        self::assertTrue($payload['created']);
        self::assertSame('MAJI', $payload['key']);
        self::assertSame('Maji', $payload['name']);
        self::assertSame($path, $payload['path']);
        self::assertSame([], $payload['activeTickets']);
    }

    public function testResolveIsIdempotentOverHttp(): void
    {
        $token = $this->createToken();
        $path = $this->uniquePath('maji');

        $this->request('POST', '/api/projects/resolve', ['path' => $path, 'name' => 'Maji'], $token);
        $first = $this->responsePayload();

        $this->request('POST', '/api/projects/resolve', ['path' => $path, 'name' => 'Maji'], $token);
        $second = $this->responsePayload();

        self::assertResponseIsSuccessful();
        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['key'], $second['key']);
    }

    public function testResolveNormalisesThePathBeforeComparingIt(): void
    {
        $token = $this->createToken();
        $path = $this->uniquePath('maji');

        $this->request('POST', '/api/projects/resolve', ['path' => $path], $token);
        $this->request('POST', '/api/projects/resolve', ['path' => $path.'/'], $token);

        self::assertFalse($this->responsePayload()['created']);
        self::assertSame($path, $this->responsePayload()['path']);
    }

    /**
     * The whole point of returning them: an agent reads this list before opening
     * anything, which is what keeps it from duplicating work already under way.
     */
    public function testResolveHandsBackTheActiveTicketsAndLeavesOutTheClosedOnes(): void
    {
        $token = $this->createToken();
        $path = $this->uniquePath('maji');

        $this->request('POST', '/api/projects/resolve', ['path' => $path], $token);
        $key = $this->responsePayload()['key'];

        $this->request('POST', sprintf('/api/projects/%s/tickets', $key), [
            'title' => 'CSV export of statements',
            'type' => 'feature',
        ], $token);
        $open = $this->responsePayload()['key'];

        $this->request('POST', sprintf('/api/projects/%s/tickets', $key), [
            'title' => 'Auth SSO',
            'type' => 'feature',
        ], $token);
        $closed = $this->responsePayload()['key'];

        $this->request('PATCH', '/api/tickets/'.$closed, ['status' => 'done', 'resolution' => 'fixed'], $token);

        $this->request('POST', '/api/projects/resolve', ['path' => $path], $token);
        $activeKeys = array_column($this->responsePayload()['activeTickets'], 'key');

        self::assertSame([$open], $activeKeys);
    }

    public function testResolveWithoutAPathIsAValidationProblem(): void
    {
        $this->request('POST', '/api/projects/resolve', ['name' => 'Maji'], $this->createToken());

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', ProblemJsonResponseFactory::CONTENT_TYPE);

        $payload = $this->responsePayload();

        self::assertSame(ProblemType::VALIDATION_FAILED, $payload['type']);
        self::assertArrayHasKey('path', $payload['errors']);
    }

    public function testResolveWithARelativePathIsRefused(): void
    {
        $this->request('POST', '/api/projects/resolve', ['path' => 'www/maji'], $this->createToken());

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ProblemType::INVALID_PROJECT_PATH, $this->responsePayload()['type']);
    }

    public function testTheProjectListReportsTheActiveTicketCount(): void
    {
        $token = $this->createToken();
        $path = $this->uniquePath('maji');

        $this->request('POST', '/api/projects/resolve', ['path' => $path], $token);
        $key = $this->responsePayload()['key'];

        foreach (['Export CSV', 'Auth SSO'] as $title) {
            $this->request('POST', sprintf('/api/projects/%s/tickets', $key), [
                'title' => $title,
                'type' => 'feature',
            ], $token);
        }

        $this->request('GET', '/api/projects', token: $token);

        $projects = array_column($this->responsePayload()['projects'], null, 'key');

        self::assertArrayHasKey($key, $projects);
        self::assertSame(2, $projects[$key]['activeTickets']);
    }

    public function testAnUnknownProjectKeyIsANotFoundProblem(): void
    {
        $this->request('GET', '/api/projects/NOPE', token: $this->createToken());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', ProblemJsonResponseFactory::CONTENT_TYPE);
        self::assertSame(ProblemType::NOT_FOUND, $this->responsePayload()['type']);
    }

    public function testTicketNumbersRunFromOneWithinTheProject(): void
    {
        $token = $this->createToken();

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $token);
        $key = $this->responsePayload()['key'];

        $keys = [];
        for ($i = 0; $i < 3; ++$i) {
            $this->request('POST', sprintf('/api/projects/%s/tickets', $key), [
                'title' => 'Ticket '.$i,
                'type' => 'chore',
            ], $token);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            $keys[] = $this->responsePayload()['key'];
        }

        self::assertSame([$key.'-1', $key.'-2', $key.'-3'], $keys);
    }

    public function testEachProjectHasItsOwnNumbering(): void
    {
        $token = $this->createToken();

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('alpha')], $token);
        $alpha = $this->responsePayload()['key'];

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('beta')], $token);
        $beta = $this->responsePayload()['key'];

        $this->request('POST', sprintf('/api/projects/%s/tickets', $alpha), ['title' => 'A', 'type' => 'bug'], $token);
        $this->request('POST', sprintf('/api/projects/%s/tickets', $beta), ['title' => 'B', 'type' => 'bug'], $token);

        self::assertSame($beta.'-1', $this->responsePayload()['key']);
    }

    public function testATicketWithAnUnknownTypeIsRefused(): void
    {
        $token = $this->createToken();

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $token);
        $key = $this->responsePayload()['key'];

        $this->request('POST', sprintf('/api/projects/%s/tickets', $key), [
            'title' => 'Export CSV',
            'type' => 'epic',
        ], $token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('type', $this->responsePayload()['errors']);
    }

    public function testTheCreatedTicketCreditsTheAgentBehindTheToken(): void
    {
        $token = $this->createToken(agentSlug: 'claude-design');

        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $token);
        $key = $this->responsePayload()['key'];

        $this->request('POST', sprintf('/api/projects/%s/tickets', $key), [
            'title' => 'Palette rework',
            'type' => 'chore',
        ], $token);

        $createdBy = $this->responsePayload()['createdBy'];

        self::assertSame('agent', $createdBy['type']);
        self::assertSame('claude-design', $createdBy['slug']);
    }
}
