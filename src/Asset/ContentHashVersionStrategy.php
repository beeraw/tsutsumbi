<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

use function hash_file;
use function is_file;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Stamps every asset URL with a hash of the file it points at.
 *
 * There is no build step here — the stylesheet and the script are hand-written
 * and served as they are — so there is no manifest to read a version from, and
 * a version bumped by hand is a version somebody forgets on the one deployment
 * that needed it. The file's own content is the only source of truth that
 * cannot fall out of step: identical bytes keep the same URL and stay in the
 * browser's cache, changed bytes get a new one and are fetched.
 *
 * The modification time was the other candidate and it is worse: a checkout
 * touches files whose content did not change, which throws away a cache for
 * nothing, and two servers would disagree on the same release.
 *
 * The cost is one hash per file per request, kept for the length of the
 * request. On a page that references eight assets of a few dozen kilobytes
 * that is noise, and it is the reason this can stay a runtime concern instead
 * of becoming a deployment step.
 */
final class ContentHashVersionStrategy implements VersionStrategyInterface
{
    /**
     * @var array<string, string>
     */
    private array $versions = [];

    public function __construct(private readonly string $publicDir)
    {
    }

    public function getVersion(string $path): string
    {
        return $this->versions[$path] ??= $this->hash($path);
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        if ('' === $version) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'v='.$version;
    }

    /**
     * An empty version leaves the URL alone. That covers the two cases where
     * stamping would be wrong rather than merely useless: an asset served from
     * somewhere else, and a path that does not resolve to a file on disk —
     * which is a broken link either way, and one that is easier to recognise
     * without a hash glued to it.
     */
    private function hash(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return '';
        }

        $file = $this->publicDir.'/'.ltrim($path, '/');

        if (!is_file($file)) {
            return '';
        }

        return substr((string) hash_file('xxh128', $file), 0, 12);
    }
}
