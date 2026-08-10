<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\InterfaceTestCase;
use Symfony\Component\HttpFoundation\Response;

final class SecurityControllerTest extends InterfaceTestCase
{
    public function testAnAnonymousVisitorIsSentToTheLoginForm(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testTheLoginFormIsPublic(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="email"]'));
        self::assertCount(1, $crawler->filter('input[name="_csrf_token"]'));
    }

    public function testTheRightCredentialsOpenTheDashboard(): void
    {
        $user = $this->createUser();
        $crawler = $this->client->request('GET', '/login');

        $this->client->submit($crawler->selectButton('Sign in')->form([
            'email' => $user->getEmail(),
            'password' => self::PASSWORD,
        ]));

        self::assertResponseRedirects('/');

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Projects');
    }

    public function testTheWrongPasswordIsRefused(): void
    {
        $user = $this->createUser();
        $crawler = $this->client->request('GET', '/login');

        $this->client->submit($crawler->selectButton('Sign in')->form([
            'email' => $user->getEmail(),
            'password' => 'not the password',
        ]));

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.login-alert');
    }

    /**
     * Without the token the form is a place to replay a submission from
     * anywhere, which is exactly what the session firewall must not allow.
     */
    public function testASubmissionWithoutACsrfTokenIsRefused(): void
    {
        $user = $this->createUser();

        $this->client->request('POST', '/login', [
            'email' => $user->getEmail(),
            'password' => self::PASSWORD,
        ]);

        $this->client->followRedirect();

        self::assertSelectorExists('.login-alert');
    }

    public function testAnAuthenticatedVisitorIsNotShownTheLoginFormAgain(): void
    {
        $this->login();

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    /**
     * The two firewalls are separate: a browser session is no more an API
     * credential than a token is a way into the interface.
     *
     * The login goes through the real form rather than loginUser(), which puts a
     * token straight into the storage and bypasses the very firewall boundary
     * this test exists to check.
     */
    public function testTheApiFirewallDoesNotAcceptASessionUser(): void
    {
        $user = $this->createUser();
        $crawler = $this->client->request('GET', '/login');

        $this->client->submit($crawler->selectButton('Sign in')->form([
            'email' => $user->getEmail(),
            'password' => self::PASSWORD,
        ]));

        self::assertResponseRedirects('/');

        $this->client->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
