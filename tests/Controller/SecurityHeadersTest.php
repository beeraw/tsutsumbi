<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

final class SecurityHeadersTest extends ApiTestCase
{
    public function testTheHardeningHeadersAreOnEveryResponse(): void
    {
        $this->request('GET', '/api/projects', token: $this->createToken());

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function testTheyAreThereOnAnErrorResponseToo(): void
    {
        $this->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    public function testStrictTransportSecurityIsOnlySentOverHttps(): void
    {
        $token = $this->createToken();

        $this->client->request('GET', 'https://tsutsumbi/api/projects', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseHasHeader('Strict-Transport-Security');

        $this->client->request('GET', 'http://tsutsumbi/api/projects', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseNotHasHeader('Strict-Transport-Security');
    }

    public function testTheInterfaceCarriesAContentSecurityPolicy(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('Content-Security-Policy');
        self::assertStringContainsString(
            "default-src 'self'",
            (string) $this->client->getResponse()->headers->get('Content-Security-Policy'),
        );
    }

    /**
     * A policy about scripts and stylesheets has nothing to act on in a JSON
     * response read by a machine.
     */
    public function testTheApiIsLeftWithoutOne(): void
    {
        $this->request('GET', '/api/projects', token: $this->createToken());

        self::assertResponseNotHasHeader('Content-Security-Policy');
    }
}
