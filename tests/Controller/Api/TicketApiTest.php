<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Enum\ApiTokenScope;
use App\Http\ProblemType;
use App\Tests\ApiTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;

use const DATE_ATOM;

final class TicketApiTest extends ApiTestCase
{
    private string $token;
    private string $projectKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->createToken();
        $this->request('POST', '/api/projects/resolve', ['path' => $this->uniquePath('maji')], $this->token);
        $this->projectKey = $this->responsePayload()['key'];
    }

    public function testATicketIsCreatedAsTodoWithoutAResolution(): void
    {
        $ticket = $this->createTicketOverHttp('CSV export of statements');

        self::assertSame('todo', $ticket['status']);
        self::assertNull($ticket['resolution']);
        self::assertSame('normal', $ticket['priority']);
    }

    public function testTheTicketDetailIsReadableByItsKey(): void
    {
        $key = $this->createTicketOverHttp('CSV export of statements')['key'];

        $this->request('GET', '/api/tickets/'.$key, token: $this->token);

        self::assertResponseIsSuccessful();
        self::assertSame($key, $this->responsePayload()['key']);
    }

    public function testAnUnknownTicketKeyIsANotFoundProblem(): void
    {
        $this->request('GET', '/api/tickets/'.$this->projectKey.'-999', token: $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(ProblemType::NOT_FOUND, $this->responsePayload()['type']);
    }

    public function testClosingWithoutAResolutionIsRefusedOverHttp(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['status' => 'done'], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ProblemType::INVALID_TICKET_STATE, $this->responsePayload()['type']);
    }

    public function testAnOpenStatusWithAResolutionIsRefusedOverHttp(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, [
            'status' => 'in_progress',
            'resolution' => 'fixed',
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ProblemType::INVALID_TICKET_STATE, $this->responsePayload()['type']);
    }

    public function testBlockingWithoutANoteIsRefusedOverHttp(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['status' => 'blocked'], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(ProblemType::INVALID_TICKET_STATE, $this->responsePayload()['type']);
    }

    public function testBlockingWithANoteRecordsBothAtOnce(): void
    {
        $key = $this->createTicketOverHttp('Auth SSO')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, [
            'status' => 'blocked',
            'note' => 'Waiting on the OAuth client from the provider.',
        ], $this->token);

        self::assertResponseIsSuccessful();

        $payload = $this->responsePayload();

        self::assertSame('blocked', $payload['status']);
        self::assertCount(1, $payload['comments']);
        self::assertStringContainsString('OAuth', $payload['comments'][0]['body']);
    }

    public function testAStatusChangeShowsUpInTheTimeline(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['status' => 'in_progress'], $this->token);

        $events = $this->responsePayload()['events'];

        self::assertCount(1, $events);
        self::assertSame('status', $events[0]['field']);
        self::assertSame('todo', $events[0]['oldValue']);
        self::assertSame('in_progress', $events[0]['newValue']);
    }

    public function testReopeningAClosedTicketClearsItsResolution(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['status' => 'done', 'resolution' => 'fixed'], $this->token);
        self::assertResponseIsSuccessful();

        $this->request('PATCH', '/api/tickets/'.$key, ['status' => 'in_progress'], $this->token);

        $payload = $this->responsePayload();

        self::assertSame('in_progress', $payload['status']);
        self::assertNull($payload['resolution']);
        self::assertNull($payload['closedAt']);
    }

    public function testTheAgentChoosesTheResolutionItself(): void
    {
        $key = $this->createTicketOverHttp('Export XLSX')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, [
            'status' => 'done',
            'resolution' => 'wont_do',
            'note' => 'Covered by the existing view, no real need.',
        ], $this->token);

        $payload = $this->responsePayload();

        self::assertSame('done', $payload['status']);
        self::assertSame('wont_do', $payload['resolution']);
        self::assertCount(1, $payload['comments']);
    }

    public function testSendingADescriptionAsNullClearsIt(): void
    {
        $key = $this->createTicketOverHttp('Export CSV', 'Le besoin initial')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['description' => null], $this->token);

        self::assertNull($this->responsePayload()['description']);
    }

    public function testOmittingADescriptionLeavesItAlone(): void
    {
        $key = $this->createTicketOverHttp('Export CSV', 'Le besoin initial')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['priority' => 'high'], $this->token);

        $payload = $this->responsePayload();

        self::assertSame('Le besoin initial', $payload['description']);
        self::assertSame('high', $payload['priority']);
    }

    public function testAnUnknownStatusIsAValidationProblem(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('PATCH', '/api/tickets/'.$key, ['status' => 'almost_done'], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('status', $this->responsePayload()['errors']);
    }

    public function testACommentCanBeAddedOnItsOwn(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('POST', sprintf('/api/tickets/%s/comments', $key), [
            'body' => 'VAT calculation stays out of scope.',
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('VAT calculation stays out of scope.', $this->responsePayload()['body']);
    }

    public function testAnEmptyCommentIsRefused(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];

        $this->request('POST', sprintf('/api/tickets/%s/comments', $key), ['body' => '   '], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCommentingNeedsItsOwnScope(): void
    {
        $key = $this->createTicketOverHttp('Export CSV')['key'];
        $narrow = $this->createToken([ApiTokenScope::TicketsWrite]);

        $this->request('POST', sprintf('/api/tickets/%s/comments', $key), ['body' => 'Note'], $narrow);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTheListingCanBeNarrowedToOneProject(): void
    {
        $this->createTicketOverHttp('Export CSV');
        $this->createTicketOverHttp('Auth SSO');

        $this->request('GET', '/api/tickets?project='.$this->projectKey, token: $this->token);

        $payload = $this->responsePayload();

        self::assertSame(2, $payload['total']);
        self::assertCount(2, $payload['tickets']);
    }

    public function testTheStatusFilterAcceptsSeveralValues(): void
    {
        $blocked = $this->createTicketOverHttp('Auth SSO')['key'];
        $this->createTicketOverHttp('Export CSV');
        $done = $this->createTicketOverHttp('Recette')['key'];

        $this->request('PATCH', '/api/tickets/'.$blocked, ['status' => 'blocked', 'note' => 'Blocked.'], $this->token);
        $this->request('PATCH', '/api/tickets/'.$done, ['status' => 'done', 'resolution' => 'fixed'], $this->token);

        $this->request(
            'GET',
            sprintf('/api/tickets?project=%s&status[]=todo&status[]=blocked', $this->projectKey),
            token: $this->token,
        );

        self::assertSame(2, $this->responsePayload()['total']);
    }

    public function testTheStatusFilterAlsoAcceptsACommaSeparatedList(): void
    {
        $done = $this->createTicketOverHttp('Recette')['key'];
        $this->createTicketOverHttp('Export CSV');

        $this->request('PATCH', '/api/tickets/'.$done, ['status' => 'done', 'resolution' => 'fixed'], $this->token);

        $this->request(
            'GET',
            sprintf('/api/tickets?project=%s&status=done', $this->projectKey),
            token: $this->token,
        );

        self::assertSame(1, $this->responsePayload()['total']);
    }

    /**
     * A closed ticket stays fully readable and searchable — the tool archives,
     * it does not delete.
     */
    public function testClosedTicketsRemainInTheUnfilteredListing(): void
    {
        $done = $this->createTicketOverHttp('Recette')['key'];
        $this->request('PATCH', '/api/tickets/'.$done, ['status' => 'done', 'resolution' => 'fixed'], $this->token);

        $this->request('GET', '/api/tickets?project='.$this->projectKey, token: $this->token);

        self::assertSame(1, $this->responsePayload()['total']);
    }

    public function testTheListingIsPaginated(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->createTicketOverHttp('Ticket '.$i);
        }

        $this->request('GET', sprintf('/api/tickets?project=%s&perPage=2&page=2', $this->projectKey), token: $this->token);

        $payload = $this->responsePayload();

        self::assertSame(5, $payload['total']);
        self::assertSame(3, $payload['pageCount']);
        self::assertCount(2, $payload['tickets']);
    }

    public function testAnUnreasonablePageSizeIsRefused(): void
    {
        $this->request('GET', '/api/tickets?perPage=5000', token: $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('perPage', $this->responsePayload()['errors']);
    }

    public function testTheListingCanBeNarrowedToWhatMovedRecently(): void
    {
        $this->createTicketOverHttp('Export CSV');

        $future = (new DateTimeImmutable('+1 hour'))->format(DATE_ATOM);
        $this->request('GET', '/api/tickets?updatedSince='.urlencode($future), token: $this->token);

        self::assertSame(0, $this->responsePayload()['total']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createTicketOverHttp(string $title, ?string $description = null): array
    {
        $this->request('POST', sprintf('/api/projects/%s/tickets', $this->projectKey), [
            'title' => $title,
            'type' => 'feature',
            'description' => $description,
        ], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->responsePayload();
    }
}
