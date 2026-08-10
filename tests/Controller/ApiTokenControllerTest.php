<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ApiToken;
use App\Enum\ApiTokenScope;
use App\Repository\ApiTokenRepository;
use App\Tests\InterfaceTestCase;

final class ApiTokenControllerTest extends InterfaceTestCase
{
    public function testTokenManagementIsClosedToAnAnonymousVisitor(): void
    {
        $this->client->request('GET', '/tokens');

        self::assertResponseRedirects('/login');
    }

    /**
     * The one moment the plain token exists outside the generator. It is shown
     * once and stored nowhere readable, so a lost token is replaced, not
     * recovered.
     */
    public function testCreatingATokenShowsItOnceAndNeverAgain(): void
    {
        $this->login();

        $this->submitTokenForm('portable / claude-code', 'claude-code', [ApiTokenScope::TicketsWrite]);

        self::assertResponseRedirects('/tokens');
        $crawler = $this->client->followRedirect();

        $revealed = $crawler->filter('.token-reveal-value')->text();

        self::assertMatchesRegularExpression('/^tsu_[0-9A-Za-z]{12}_[0-9A-Za-z]{43}$/', $revealed);

        // A second visit must not show it again.
        $crawler = $this->client->request('GET', '/tokens');

        self::assertCount(0, $crawler->filter('.token-reveal-value'));
        self::assertStringNotContainsString($revealed, (string) $this->client->getResponse()->getContent());
    }

    public function testOnlyTheGrantedScopesAreStored(): void
    {
        $this->login();

        $this->submitTokenForm('lecture seule', 'claude-review', [ApiTokenScope::CommentsWrite]);
        $this->client->followRedirect();

        self::assertSame([ApiTokenScope::CommentsWrite->value], $this->findToken('lecture seule')->getScopes());
    }

    public function testATokenWithoutAScopeIsRefused(): void
    {
        $this->login();

        $this->submitTokenForm('no scope', 'claude-code', []);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.form-error-messages');
    }

    public function testAnAgentSlugThatIsNotASlugIsRefused(): void
    {
        $this->login();

        $this->submitTokenForm('mauvais slug', 'Claude Code!', [ApiTokenScope::TicketsWrite]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error-messages', 'lowercase letters');
    }

    public function testRevokingATokenClosesTheDoorBehindIt(): void
    {
        $this->login();

        $this->submitTokenForm('to revoke', 'claude-code', [ApiTokenScope::TicketsWrite]);
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->filter('form[action$="/revoke"] button')->form());

        self::assertResponseRedirects('/tokens');
        $this->client->followRedirect();

        self::assertTrue($this->findToken('to revoke')->isRevoked());
    }

    public function testRevokingWithoutACsrfTokenIsRefused(): void
    {
        $this->login();

        $this->submitTokenForm('protected', 'claude-code', [ApiTokenScope::TicketsWrite]);
        $this->client->followRedirect();

        $this->client->request('POST', '/tokens/'.$this->findToken('protected')->getSelector().'/revoke');

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->findToken('protected')->isRevoked());
    }

    /**
     * Posted by hand rather than through the crawler: the scopes are a set of
     * checkboxes sharing one name, which DomCrawler collapses into a single
     * choice field.
     *
     * @param list<ApiTokenScope> $scopes
     */
    private function submitTokenForm(string $label, string $agentSlug, array $scopes): void
    {
        $crawler = $this->client->request('GET', '/tokens');
        $csrf = $crawler->filter('input[name="api_token_form[_token]"]')->attr('value');

        $this->client->request('POST', '/tokens', [
            'api_token_form' => [
                'label' => $label,
                'agentSlug' => $agentSlug,
                'scopes' => array_map(static fn (ApiTokenScope $scope): string => $scope->value, $scopes),
                'expiresAt' => '',
                '_token' => $csrf,
            ],
        ]);
    }

    private function findToken(string $label): ApiToken
    {
        $token = static::getContainer()->get(ApiTokenRepository::class)->findOneBy(['label' => $label]);

        self::assertInstanceOf(ApiToken::class, $token);

        return $token;
    }
}
