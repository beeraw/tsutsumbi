<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ContentSecurityPolicy;
use PHPUnit\Framework\TestCase;

use function in_array;

final class ContentSecurityPolicyTest extends TestCase
{
    /**
     * The policy that ships. Everything the interface needs is same-origin — the
     * font is self-hosted, the stylesheet and the script are files — so no
     * inline anything has to be allowed.
     */
    public function testTheShippedPolicyAllowsNoInlineScriptOrStyleBlock(): void
    {
        $policy = (new ContentSecurityPolicy())->build(debug: false);

        self::assertStringNotContainsString('unsafe-eval', $policy);
        self::assertStringContainsString("script-src 'self'", $policy);
        self::assertStringContainsString("style-src 'self'", $policy);
        self::assertStringContainsString("font-src 'self'", $policy);
    }

    /**
     * The single relaxation that ships, and it is the narrowest one available:
     * style attributes carry an actor's colour, while <style> blocks and remote
     * stylesheets stay refused.
     */
    public function testStyleAttributesAreAllowedButStyleBlocksAreNot(): void
    {
        $policy = (new ContentSecurityPolicy())->build(debug: false);

        self::assertStringContainsString("style-src-attr 'unsafe-inline'", $policy);
        self::assertStringContainsString("style-src 'self';", $policy);
        self::assertStringNotContainsString("style-src 'self' 'unsafe-inline'", $policy);
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    public function testItForbidsFramingAndBaseTagHijacking(): void
    {
        $policy = (new ContentSecurityPolicy())->build(debug: false);

        self::assertStringContainsString("frame-ancestors 'none'", $policy);
        self::assertStringContainsString("base-uri 'none'", $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("form-action 'self'", $policy);
    }

    /**
     * The web debug toolbar injects its own inline script and style. The
     * relaxation is deliberate and never leaves a developer's machine.
     */
    public function testDebugModeMakesRoomForTheProfiler(): void
    {
        $policy = (new ContentSecurityPolicy())->build(debug: true);

        self::assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $policy);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $policy);
    }

    /**
     * Debug mode must not quietly widen anything beyond what the profiler needs.
     */
    public function testDebugModeTouchesOnlyScriptAndStyleSources(): void
    {
        $strict = self::directives((new ContentSecurityPolicy())->build(debug: false));
        $relaxed = self::directives((new ContentSecurityPolicy())->build(debug: true));

        self::assertSame(array_keys($strict), array_keys($relaxed));

        foreach ($strict as $name => $sources) {
            if (in_array($name, ['script-src', 'style-src'], true)) {
                continue;
            }

            self::assertSame($sources, $relaxed[$name], sprintf('"%s" must be identical in both modes.', $name));
        }
    }

    /**
     * @return array<string, string>
     */
    private static function directives(string $policy): array
    {
        $directives = [];

        foreach (explode('; ', $policy) as $part) {
            [$name, $sources] = explode(' ', $part, 2) + [1 => ''];
            $directives[$name] = $sources;
        }

        return $directives;
    }
}
