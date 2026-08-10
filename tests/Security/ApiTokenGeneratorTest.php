<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\ApiToken;
use App\Security\ApiTokenGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

final class ApiTokenGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'no separator' => ['tsuabcdefghijkl'];
        yield 'wrong prefix' => ['tok_abcdefghijkl_'.str_repeat('a', 43)];
        yield 'too many parts' => ['tsu_abcdefghijkl_'.str_repeat('a', 43).'_extra'];
        yield 'short selector' => ['tsu_abc_'.str_repeat('a', 43)];
        yield 'short verifier' => ['tsu_abcdefghijkl_abc'];
        yield 'non base62 characters' => ['tsu_abcdefghij-l_'.str_repeat('a', 43)];
    }

    public function testItMintsATokenInTheDocumentedShape(): void
    {
        $generated = (new ApiTokenGenerator())->generate();

        self::assertMatchesRegularExpression(
            '/^tsu_[0-9A-Za-z]{12}_[0-9A-Za-z]{43}$/',
            $generated->plain,
        );
        self::assertSame(ApiToken::SELECTOR_LENGTH, strlen($generated->selector));
        self::assertStringContainsString($generated->selector, $generated->plain);
    }

    public function testTheVerifierIsStoredHashedAndNeverInTheClear(): void
    {
        $generator = new ApiTokenGenerator();
        $generated = $generator->generate();
        $parsed = $generator->parse($generated->plain);

        self::assertNotNull($parsed);
        self::assertStringNotContainsString($parsed->verifier, $generated->verifierHash);
        self::assertTrue(password_verify($parsed->verifier, $generated->verifierHash));
    }

    public function testTwoTokensNeverCollide(): void
    {
        $generator = new ApiTokenGenerator();
        $selectors = [];

        for ($i = 0; $i < 200; ++$i) {
            $selectors[] = $generator->generate()->selector;
        }

        self::assertCount(200, array_unique($selectors));
    }

    public function testItSplitsAValidTokenBackIntoItsTwoHalves(): void
    {
        $generator = new ApiTokenGenerator();
        $generated = $generator->generate();
        $parsed = $generator->parse($generated->plain);

        self::assertNotNull($parsed);
        self::assertSame($generated->selector, $parsed->selector);
        self::assertSame(ApiToken::VERIFIER_LENGTH, strlen($parsed->verifier));
    }

    #[DataProvider('malformedTokens')]
    public function testItRefusesAnythingThatIsNotAToken(string $candidate): void
    {
        self::assertNull((new ApiTokenGenerator())->parse($candidate));
    }
}
