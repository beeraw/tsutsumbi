<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Renders the markdown of descriptions and comments.
 *
 * Raw HTML in the source is escaped rather than passed through: the text comes
 * from agents and from a form, and neither is a place to accept markup. That is
 * also what lets the output be marked safe for Twig without widening the CSP.
 */
final class MarkdownExtension extends AbstractExtension
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 25,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->toHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function toHtml(?string $markdown): string
    {
        if (null === $markdown || '' === trim($markdown)) {
            return '';
        }

        return $this->converter->convert($markdown)->getContent();
    }
}
