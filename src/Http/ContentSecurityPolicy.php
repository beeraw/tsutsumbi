<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The policy for the interface. Everything it needs is served from the same
 * origin — the font is self-hosted, the stylesheet and the script are files —
 * so nothing has to be opened up for a third party.
 */
final readonly class ContentSecurityPolicy
{
    private const array DIRECTIVES = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'"],
        'style-src' => ["'self'"],
        // Style *attributes* only, and deliberately: an actor's colour is data,
        // so it lands on the element as a custom property. Without this the
        // directive falls back to style-src and every agent loses its colour.
        // <style> blocks and remote stylesheets stay blocked, which is where the
        // protection actually matters.
        'style-src-attr' => ["'unsafe-inline'"],
        'font-src' => ["'self'"],
        // data: covers the inline SVG favicon fallback; no remote image is loaded.
        'img-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
        'base-uri' => ["'none'"],
        'object-src' => ["'none'"],
    ];

    /**
     * The web debug toolbar and the profiler inject their own inline script and
     * style, so debug mode has to allow them. The strict policy is what ships;
     * this relaxation never leaves a developer's machine.
     */
    public function build(bool $debug): string
    {
        $directives = self::DIRECTIVES;

        if ($debug) {
            $directives['script-src'][] = "'unsafe-inline'";
            $directives['script-src'][] = "'unsafe-eval'";
            $directives['style-src'][] = "'unsafe-inline'";
        }

        $parts = [];
        foreach ($directives as $name => $sources) {
            $parts[] = $name.' '.implode(' ', $sources);
        }

        return implode('; ', $parts);
    }
}
