<?php

declare(strict_types=1);

namespace App\Domain;

use App\Exception\InvalidProjectPathException;

/**
 * Brings a project path to the single canonical form used for identity.
 *
 * The normalisation is purely lexical, on purpose. tsutsumbi does not
 * necessarily run on the machine that holds the project, so touching the
 * filesystem here would make the result depend on where the server happens to
 * live — and a path that resolves differently tomorrow would silently create a
 * second project for the same directory, which is exactly what resolve() must
 * never do. Resolving symlinks is the job of the MCP server, which does run
 * next to the files, and which sends the resolved path.
 */
final class PathNormalizer
{
    public const int MAX_LENGTH = 500;

    /**
     * @throws InvalidProjectPathException
     */
    public function normalize(string $path): string
    {
        $path = trim($path);

        if ('' === $path) {
            throw InvalidProjectPathException::empty();
        }

        if (!str_starts_with($path, '/')) {
            throw InvalidProjectPathException::notAbsolute($path);
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    throw InvalidProjectPathException::escapesRoot($path);
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = '/'.implode('/', $segments);

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw InvalidProjectPathException::tooLong($normalized, self::MAX_LENGTH);
        }

        return $normalized;
    }

    /**
     * The directory name, which is what both the project key and the default
     * project name are derived from.
     */
    public function basename(string $normalizedPath): string
    {
        $position = strrpos($normalizedPath, '/');

        return false === $position ? $normalizedPath : substr($normalizedPath, $position + 1);
    }
}
