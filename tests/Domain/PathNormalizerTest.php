<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\PathNormalizer;
use App\Exception\InvalidProjectPathException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizedPaths(): iterable
    {
        yield 'already canonical' => ['/home/dev/projects/maji', '/home/dev/projects/maji'];
        yield 'trailing slash' => ['/home/dev/projects/maji/', '/home/dev/projects/maji'];
        yield 'repeated slashes' => ['/home//dev///projects/maji', '/home/dev/projects/maji'];
        yield 'current directory segments' => ['/home/dev/./projects/./maji', '/home/dev/projects/maji'];
        yield 'parent directory segments' => ['/home/dev/projects/harusi/../maji', '/home/dev/projects/maji'];
        yield 'surrounding whitespace' => ['  /home/dev/projects/maji  ', '/home/dev/projects/maji'];
        yield 'root itself' => ['/', '/'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'relative' => ['www/maji'];
        yield 'home shorthand' => ['~/www/maji'];
        yield 'above the root' => ['/Users/../..'];
    }

    #[DataProvider('normalizedPaths')]
    public function testItBringsAPathToItsCanonicalForm(string $input, string $expected): void
    {
        self::assertSame($expected, (new PathNormalizer())->normalize($input));
    }

    #[DataProvider('rejectedPaths')]
    public function testItRejectsAPathItCannotIdentifyAProjectBy(string $input): void
    {
        $this->expectException(InvalidProjectPathException::class);

        (new PathNormalizer())->normalize($input);
    }

    public function testNormalisationIsIdempotent(): void
    {
        $normalizer = new PathNormalizer();
        $once = $normalizer->normalize('/home/dev/projects//harusi/../maji/');

        self::assertSame($once, $normalizer->normalize($once));
    }

    public function testItRejectsAPathLongerThanTheColumn(): void
    {
        $this->expectException(InvalidProjectPathException::class);

        (new PathNormalizer())->normalize('/'.str_repeat('a', PathNormalizer::MAX_LENGTH));
    }

    public function testItReadsTheDirectoryName(): void
    {
        self::assertSame('maji', (new PathNormalizer())->basename('/home/dev/projects/maji'));
    }
}
