<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Domain\TicketChange;
use App\Domain\TicketWorkflow;
use App\Entity\Project;
use App\Entity\Ticket;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Enum\TicketType;
use App\Tests\InterfaceTestCase;
use Symfony\Component\DomCrawler\Crawler;

use function sprintf;
use function trim;

final class ProjectControllerTest extends InterfaceTestCase
{
    public function testTheDashboardListsProjectsWithTheirActiveTicketCount(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $this->createTicket($project, 'Import CSV');
        $this->createTicket($project, 'Auth SSO');

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $row = $crawler->filter(sprintf('.grid-row:contains("%s")', $project->getKey()));

        self::assertCount(1, $row);
        self::assertStringContainsString('2', $row->filter('.c-active')->text());
    }

    public function testAnArchivedProjectIsOutOfTheWayUntilAskedFor(): void
    {
        $this->login();
        $project = $this->createProject('kivuli');
        $project->archive();
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('body', $project->getKey());

        $crawler = $this->client->request('GET', '/?archived=1');
        self::assertStringContainsString($project->getKey(), $crawler->filter('body')->text());
    }

    public function testTheProjectScreenShowsItsTickets(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $ticket = $this->createTicket($project, 'Wedding sites on a subdomain');

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $project->getName());
        self::assertStringContainsString($ticket->getKey(), $crawler->filter('.grid-tickets')->text());
    }

    /**
     * The active views of the specification: a closed ticket is not deleted, it
     * is simply not in the way until asked for.
     */
    public function testClosedTicketsAreHiddenUntilTheToggleIsOn(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $closed = $this->createTicket($project, 'V1 acceptance testing');

        static::getContainer()->get(TicketWorkflow::class)->apply(
            $closed,
            $closed->getCreatedBy(),
            new TicketChange(
                [TicketChange::STATUS, TicketChange::RESOLUTION],
                status: TicketStatus::Done,
                resolution: TicketResolution::Fixed,
            ),
        );

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey());
        self::assertStringNotContainsString($closed->getKey(), $crawler->filter('body')->text());

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'?closed=1');
        self::assertStringContainsString($closed->getKey(), $crawler->filter('body')->text());
    }

    public function testTheTypeFilterNarrowsTheList(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $bug = $this->createTicket($project, 'Reminder time zone', TicketType::Bug);
        $feature = $this->createTicket($project, 'Import CSV', TicketType::Feature);

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'?type=bug');
        $body = $crawler->filter('.grid-tickets')->text();

        self::assertStringContainsString($bug->getKey(), $body);
        self::assertStringNotContainsString($feature->getKey(), $body);
    }

    /**
     * A filter value only a hand-edited URL can produce is dropped rather than
     * turned into an error page.
     */
    public function testAnUnknownFilterValueIsIgnoredRatherThanRefused(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $this->createTicket($project, 'Import CSV');

        $this->client->request('GET', '/projects/'.$project->getKey().'?type=epic&status=almost');

        self::assertResponseIsSuccessful();
    }

    public function testFollowingAColumnHeadingSortsTheListOnIt(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $this->createTicket($project, 'Room zoning');
        $this->createTicket($project, 'Auth SSO');
        $this->createTicket($project, 'Media migration');

        self::assertSame(
            ['Auth SSO', 'Media migration', 'Room zoning'],
            $this->titlesOn($project, '?sort=title&dir=asc'),
        );

        self::assertSame(
            ['Room zoning', 'Media migration', 'Auth SSO'],
            $this->titlesOn($project, '?sort=title&dir=desc'),
        );
    }

    /**
     * The key is a project and a number, and the number is an integer: sorting
     * on the rendered key would file the tenth ticket between the first and the
     * second.
     */
    public function testTheKeySortsOnTheNumberRatherThanOnTheText(): void
    {
        $this->login();
        $project = $this->createProject('harusi');

        for ($i = 1; $i <= 11; ++$i) {
            $this->createTicket($project, sprintf('Ticket %d', $i));
        }

        $keys = $this->keysOn($project, '?sort=key&dir=asc');

        self::assertSame($project->getKey().'-1', $keys[0]);
        self::assertSame($project->getKey().'-2', $keys[1]);
        self::assertSame($project->getKey().'-11', $keys[10]);
    }

    /**
     * Alphabetically a status column reads "blocked, done, in progress, todo",
     * which is an order nobody asked for — and a different wrong order in every
     * language the interface is served in. The cases are declared in the order
     * the work moves through them, and that is the order used.
     */
    public function testTheStatusSortsAlongTheLifecycleRatherThanTheAlphabet(): void
    {
        $this->login();
        $project = $this->createProject('harusi');

        $done = $this->createTicket($project, 'Done');
        $this->move($done, TicketStatus::Done, TicketResolution::Fixed);

        $blocked = $this->createTicket($project, 'Blocked');
        $this->move($blocked, TicketStatus::Blocked, note: 'The API key is missing.');

        $inProgress = $this->createTicket($project, 'In progress');
        $this->move($inProgress, TicketStatus::InProgress);

        $this->createTicket($project, 'Todo');

        self::assertSame(
            ['Todo', 'In progress', 'Blocked', 'Done'],
            $this->titlesOn($project, '?closed=1&sort=status&dir=asc'),
        );
    }

    public function testAColumnHeadingCarriesTheFiltersAndLeavesThePageBehind(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $this->createTicket($project, 'Import CSV', TicketType::Bug);

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'?type=bug&page=1');
        $href = $crawler->filter('.grid-head .grid-sort')->first()->attr('href');

        self::assertNotNull($href);
        self::assertStringContainsString('type=bug', $href);
        self::assertStringContainsString('sort=key', $href);
        self::assertStringNotContainsString('page=', $href);
    }

    /**
     * The heading of the column in force turns the sort around; every other one
     * offers its own first direction — descending for a date, so that following
     * "Mis à jour" never lands on the oldest ticket in the project.
     */
    public function testTheSortedHeadingOffersToTurnTheSortAround(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $this->createTicket($project, 'Import CSV');

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey());
        $active = $crawler->filter('.grid-head .grid-sort.is-active');

        self::assertCount(1, $active);
        self::assertStringContainsString('sort=updated', (string) $active->attr('href'));
        self::assertStringContainsString('dir=asc', (string) $active->attr('href'));

        $key = $crawler->filter('.grid-head .grid-sort')->first();

        self::assertStringContainsString('sort=key', (string) $key->attr('href'));
        self::assertStringContainsString('dir=asc', (string) $key->attr('href'));
    }

    public function testAnUnknownSortFallsBackToTheDefaultListing(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $this->createTicket($project, 'Import CSV');

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'?sort=colour&dir=sideways');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.grid-head .grid-sort.is-active'));
    }

    public function testArchivingAProjectAndPuttingItBack(): void
    {
        $this->login();
        $project = $this->createProject('harusi');

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey());
        $this->client->submit($crawler->selectButton('Archive the project')->form());

        self::assertResponseRedirects('/projects/'.$project->getKey());
        $crawler = $this->client->followRedirect();

        $reloaded = $this->entityManager->find(Project::class, $project->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isArchived());

        $this->client->submit($crawler->selectButton('Unarchive the project')->form());
        $this->client->followRedirect();

        $this->entityManager->refresh($reloaded);
        self::assertFalse($reloaded->isArchived());
    }

    public function testArchivingWithoutACsrfTokenIsRefused(): void
    {
        $this->login();
        $project = $this->createProject('harusi');

        $this->client->request('POST', '/projects/'.$project->getKey().'/archive');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheProjectScreenOffersAWayToEditIt(): void
    {
        $this->login();
        $project = $this->createProject('harusi');

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey());

        self::assertCount(1, $crawler->filter(sprintf('a[href="/projects/%s/edit"]', $project->getKey())));
    }

    public function testTheNameAndTheDescriptionAreEditable(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $id = $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[name]'] = 'Harusi ya Mayotte';
        $form['project_edit_form[description]'] = 'Wedding sites.';

        $this->client->submit($form);
        $this->client->followRedirect();

        $reloaded = $this->reload(Project::class, $id);

        self::assertSame('Harusi ya Mayotte', $reloaded->getName());
        self::assertSame('Wedding sites.', $reloaded->getDescription());
    }

    public function testTheDescriptionIsShownOnTheProjectScreen(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $project->setDescription('Wedding sites on a subdomain.');
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey());

        self::assertStringContainsString(
            'Wedding sites on a subdomain.',
            $crawler->filter('.page-head-description')->text(),
        );
    }

    public function testMovingAProjectNormalisesTheNewPath(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $id = $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[path]'] = '/tmp/tsutsumbi-tests/moved/./harusi/';

        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSame('/tmp/tsutsumbi-tests/moved/harusi', $this->reload(Project::class, $id)->getPath());
    }

    /**
     * The rename is allowed, and the reader is told what it costs rather than
     * being stopped: the ticket identifiers are derived from this key.
     */
    public function testRenamingTheKeyRewritesTheTicketIdentifiers(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $ticket = $this->createTicket($project, 'Wedding sites on a subdomain');
        $ticketId = $ticket->getId();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');

        self::assertStringContainsString(
            'Tickets take their identifier from it',
            $crawler->filter('body')->text(),
        );

        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[key]'] = 'HARUSI';

        $this->client->submit($form);

        self::assertResponseRedirects('/projects/HARUSI');
        self::assertSame('HARUSI-1', $this->reload(Ticket::class, $ticketId)->getKey());
    }

    public function testAKeyAnotherProjectHoldsIsRefusedOnTheField(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $other = $this->createProject('kivuli');
        $id = $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[key]'] = $other->getKey();

        $crawler = $this->client->submit($form);

        // Symfony answers a rejected form with 422, and the page comes back
        // with the error on the field rather than a redirect.
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('already holds this key', $crawler->filter('body')->text());
        self::assertNotSame($other->getKey(), $this->reload(Project::class, $id)->getKey());
    }

    public function testAPathAnotherProjectHoldsIsRefusedOnTheField(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $other = $this->createProject('kivuli');
        $id = $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[path]'] = $other->getPath();

        $crawler = $this->client->submit($form);

        // Symfony answers a rejected form with 422, and the page comes back
        // with the error on the field rather than a redirect.
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('already tracked at this path', $crawler->filter('body')->text());
        self::assertNotSame($other->getPath(), $this->reload(Project::class, $id)->getPath());
    }

    public function testARelativePathIsRefusedOnTheField(): void
    {
        $this->login();
        $project = $this->createProject('harusi');

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[path]'] = 'tmp/harusi';

        $crawler = $this->client->submit($form);

        // Symfony answers a rejected form with 422, and the page comes back
        // with the error on the field rather than a redirect.
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('must be absolute', $crawler->filter('body')->text());
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $this->login();
        $project = $this->createProject('harusi');
        $id = $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().'/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['project_edit_form[name]'] = '  ';

        $this->client->submit($form);

        // Symfony answers a rejected form with 422, and the page comes back
        // with the error on the field rather than a redirect.
        self::assertResponseStatusCodeSame(422);
        self::assertSame('harusi', $this->reload(Project::class, $id)->getName());
    }

    public function testEditingAProjectNeedsASession(): void
    {
        $project = $this->createProject('harusi');

        $this->client->request('GET', '/projects/'.$project->getKey().'/edit');

        self::assertResponseRedirects();
    }

    public function testAnUnknownProjectIsANotFound(): void
    {
        $this->login();

        $this->client->request('GET', '/projects/NOPE');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<string>
     */
    private function titlesOn(Project $project, string $query): array
    {
        return $this->columnOn($project, $query, '.c-title');
    }

    /**
     * @return list<string>
     */
    private function keysOn(Project $project, string $query): array
    {
        return $this->columnOn($project, $query, '.c-key');
    }

    /**
     * @return list<string>
     */
    private function columnOn(Project $project, string $query, string $selector): array
    {
        $crawler = $this->client->request('GET', '/projects/'.$project->getKey().$query);

        self::assertResponseIsSuccessful();

        return $crawler->filter('.grid-row '.$selector)->each(
            static fn (Crawler $cell): string => trim($cell->text()),
        );
    }

    private function move(
        Ticket $ticket,
        TicketStatus $status,
        ?TicketResolution $resolution = null,
        ?string $note = null,
    ): void {
        $provided = [TicketChange::STATUS];

        if (null !== $resolution) {
            $provided[] = TicketChange::RESOLUTION;
        }

        if (null !== $note) {
            $provided[] = TicketChange::NOTE;
        }

        static::getContainer()->get(TicketWorkflow::class)->apply(
            $ticket,
            $ticket->getCreatedBy(),
            new TicketChange($provided, status: $status, resolution: $resolution, note: $note),
        );
    }
}
