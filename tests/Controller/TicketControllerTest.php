<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Ticket;
use App\Enum\TicketPriority;
use App\Enum\TicketResolution;
use App\Enum\TicketStatus;
use App\Repository\TicketEventRepository;
use App\Tests\InterfaceTestCase;

final class TicketControllerTest extends InterfaceTestCase
{
    public function testTheTicketScreenShowsTheDescriptionAsMarkdown(): void
    {
        $this->login();
        $ticket = $this->createTicket(description: 'The need is **clear**, the rest is `out of scope`.');

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.prose strong'));
        self::assertCount(1, $crawler->filter('.prose code'));
    }

    /**
     * Markup in a description comes from an agent or a form, and neither is a
     * place to accept it.
     */
    public function testHtmlInsideTheMarkdownIsEscapedRatherThanRendered(): void
    {
        $this->login();
        $ticket = $this->createTicket(description: 'Before <script>alert(1)</script> after');

        $this->client->request('GET', '/tickets/'.$ticket->getKey());

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEditingATicketLeavesATraceInTheTimeline(): void
    {
        $this->login();
        $ticket = $this->createTicket(title: 'Export CSV');

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());
        $form = $crawler->selectButton('Save')->form();
        $form['ticket_edit_form[title]'] = 'CSV and XLSX export';
        $form['ticket_edit_form[priority]'] = TicketPriority::High->value;

        $this->client->submit($form);

        self::assertResponseRedirects('/tickets/'.$ticket->getKey());
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('h1', 'CSV and XLSX export');
        self::assertStringContainsString('Title', $crawler->filter('.timeline')->text());
    }

    public function testMovingToInProgressIsRecorded(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $this->submitStatus($ticket, TicketStatus::InProgress);

        self::assertResponseRedirects('/tickets/'.$ticket->getKey());
        $this->client->followRedirect();

        $reloaded = $this->reload(Ticket::class, $ticket->getId());

        self::assertSame(TicketStatus::InProgress, $reloaded->getStatus());
        self::assertNotNull($reloaded->getStartedAt());
        self::assertCount(1, static::getContainer()->get(TicketEventRepository::class)->findForTicket($reloaded));
    }

    public function testBlockingWithoutANoteIsShownAsAFormError(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $this->submitStatus($ticket, TicketStatus::Blocked);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.form-error-messages',
            'Moving to blocked requires a comment explaining the cause.',
        );

        self::assertSame(TicketStatus::Todo, $this->reload(Ticket::class, $ticket->getId())->getStatus());
    }

    public function testClosingWithoutAResolutionIsShownAsAFormError(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $this->submitStatus($ticket, TicketStatus::Done);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error-messages', 'must carry a resolution');
    }

    public function testBlockingWithANoteRecordsBothTogether(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $this->submitStatus($ticket, TicketStatus::Blocked, note: 'Le certificat wildcard manque encore.');

        self::assertResponseRedirects('/tickets/'.$ticket->getKey());
        $crawler = $this->client->followRedirect();

        self::assertSame(TicketStatus::Blocked, $this->reload(Ticket::class, $ticket->getId())->getStatus());
        self::assertStringContainsString('certificat wildcard', $crawler->filter('.timeline')->text());
    }

    public function testClosingThenReopeningClearsTheResolution(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $this->submitStatus($ticket, TicketStatus::Done, TicketResolution::Fixed);
        $this->client->followRedirect();

        self::assertSame(TicketResolution::Fixed, $this->reload(Ticket::class, $ticket->getId())->getResolution());

        $this->submitStatus($ticket, TicketStatus::InProgress);
        $this->client->followRedirect();

        $reloaded = $this->reload(Ticket::class, $ticket->getId());

        self::assertSame(TicketStatus::InProgress, $reloaded->getStatus());
        self::assertNull($reloaded->getResolution());
        self::assertNull($reloaded->getClosedAt());
    }

    public function testACommentIsAddedToTheTimeline(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());
        $form = $crawler->selectButton('Comment')->form();
        $form['comment_form[body]'] = 'VAT calculation stays out of scope.';

        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('out of scope', $crawler->filter('.timeline')->text());
    }

    public function testAnEmptyCommentIsRefused(): void
    {
        $this->login();
        $ticket = $this->createTicket();

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());
        $form = $crawler->selectButton('Comment')->form();
        $form['comment_form[body]'] = '   ';

        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.form-error-messages');
    }

    public function testAnUnknownTicketIsANotFound(): void
    {
        $this->login();

        $this->client->request('GET', '/tickets/NOPE-1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheScreenIsClosedToAnAnonymousVisitor(): void
    {
        $ticket = $this->createTicket();

        $this->client->request('GET', '/tickets/'.$ticket->getKey());

        self::assertResponseRedirects('/login');
    }

    private function submitStatus(
        Ticket $ticket,
        TicketStatus $status,
        ?TicketResolution $resolution = null,
        ?string $note = null,
    ): void {
        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getKey());
        $form = $crawler->selectButton('Apply')->form();
        $form['ticket_status_form[status]'] = $status->value;
        $form['ticket_status_form[resolution]'] = null === $resolution ? '' : $resolution->value;
        $form['ticket_status_form[note]'] = $note ?? '';

        $this->client->submit($form);
    }
}
