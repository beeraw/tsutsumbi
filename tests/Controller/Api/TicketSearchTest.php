<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\ApiTestCase;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;

use function sprintf;

/**
 * Full-text search cannot be tested inside the transaction the rest of the suite
 * runs in: InnoDB only publishes a row to its FULLTEXT index on commit, so a
 * search would find nothing and the test would be meaningless.
 *
 * This class therefore commits for real and removes its own project afterwards.
 */
final class TicketSearchTest extends ApiTestCase
{
    private string $token;
    private string $projectKey;
    private string $projectPath;

    public static function setUpBeforeClass(): void
    {
        StaticDriver::setKeepStaticConnections(false);
    }

    public static function tearDownAfterClass(): void
    {
        StaticDriver::setKeepStaticConnections(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->createToken();
        $this->projectPath = $this->uniquePath('maji');

        $this->request('POST', '/api/projects/resolve', ['path' => $this->projectPath], $this->token);
        $this->projectKey = $this->responsePayload()['key'];

        $this->createTicket('CSV export of statements', 'Allow the monthly statements to be downloaded.');
        $this->createTicket('SSO authentication', 'Plug in the customer OAuth provider.');
        $this->createTicket('VAT calculation on credit notes', 'The rate applied is wrong on a partial credit note.');
    }

    protected function tearDown(): void
    {
        // Nothing rolls back here, so the fixtures are removed by hand. The
        // cascades on ticket, comment and event take care of the rest.
        static::getContainer()->get(Connection::class)->delete('project', ['path' => $this->projectPath]);

        parent::tearDown();
    }

    public function testItFindsATicketByAWordOfItsTitle(): void
    {
        self::assertSame(['CSV export of statements'], $this->search('statements'));
    }

    public function testItFindsATicketByAWordOfItsDescription(): void
    {
        self::assertSame(['SSO authentication'], $this->search('OAuth'));
    }

    public function testEveryTermIsRequiredRatherThanJustOne(): void
    {
        self::assertSame([], $this->search('OAuth statements'));
        self::assertSame(['SSO authentication'], $this->search('OAuth provider'));
    }

    public function testATermIsMatchedAsAPrefix(): void
    {
        self::assertSame(['VAT calculation on credit notes'], $this->search('calculat'));
    }

    public function testItAlsoLooksInsideTheComments(): void
    {
        $this->request('POST', sprintf('/api/tickets/%s-1/comments', $this->projectKey), [
            'body' => 'Decision: a console command rather than the controller.',
        ], $this->token);

        self::assertSame(['CSV export of statements'], $this->search('console'));
    }

    /**
     * Below innodb_ft_min_token_size the index knows nothing of the term, so the
     * query has to fall back to LIKE instead of quietly returning nothing.
     */
    public function testATermTooShortForTheIndexFallsBackToALikeSearch(): void
    {
        self::assertSame(['VAT calculation on credit notes'], $this->search('AT ca'));
    }

    public function testSearchingIsCaseInsensitive(): void
    {
        self::assertSame(['SSO authentication'], $this->search('oauth'));
    }

    public function testBooleanOperatorsInTheQueryAreTreatedAsText(): void
    {
        self::assertSame(['SSO authentication'], $this->search('+OAuth* "'));
    }

    public function testAQueryMatchingNothingReturnsAnEmptyPage(): void
    {
        self::assertSame([], $this->search('kubernetes'));
    }

    public function testAClosedTicketStaysSearchable(): void
    {
        $this->request('PATCH', sprintf('/api/tickets/%s-2', $this->projectKey), [
            'status' => 'done',
            'resolution' => 'fixed',
        ], $this->token);

        self::assertSame(['SSO authentication'], $this->search('OAuth'));
    }

    /**
     * @return list<string>
     */
    private function search(string $query): array
    {
        $this->request(
            'GET',
            sprintf('/api/tickets?project=%s&q=%s', $this->projectKey, urlencode($query)),
            token: $this->token,
        );

        self::assertResponseIsSuccessful();

        /** @var list<string> $titles */
        $titles = array_column($this->responsePayload()['tickets'], 'title');

        return $titles;
    }

    private function createTicket(string $title, string $description): void
    {
        $this->request('POST', sprintf('/api/projects/%s/tickets', $this->projectKey), [
            'title' => $title,
            'type' => 'feature',
            'description' => $description,
        ], $this->token);

        self::assertResponseStatusCodeSame(201);
    }
}
