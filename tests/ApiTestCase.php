<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ApiToken;
use App\Enum\ApiTokenScope;
use App\Security\ApiTokenGenerator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function sprintf;

use const JSON_THROW_ON_ERROR;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<ApiTokenScope> $scopes
     *
     * @return string the plain token, the only moment it exists in this form
     */
    protected function createToken(
        array $scopes = [],
        string $agentSlug = 'claude-code',
        ?DateTimeImmutable $expiresAt = null,
        bool $revoked = false,
    ): string {
        $generator = static::getContainer()->get(ApiTokenGenerator::class);
        $generated = $generator->generate();

        $token = new ApiToken(
            'test',
            $agentSlug,
            [] === $scopes ? ApiTokenScope::cases() : $scopes,
            $generated->selector,
            $generated->verifierHash,
            expiresAt: $expiresAt,
        );

        if ($revoked) {
            $token->revoke();
        }

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $generated->plain;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function request(string $method, string $uri, ?array $body = null, ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $headers,
            content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function responsePayload(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * A path nothing else in the suite will claim, so tests stay independent of
     * one another's fixtures.
     */
    protected function uniquePath(string $name): string
    {
        // The random part is a parent directory, so the last segment — the one
        // the project key is derived from — stays exactly what the caller asked.
        return sprintf('/tmp/tsutsumbi-tests/%s/%s', bin2hex(random_bytes(4)), $name);
    }
}
