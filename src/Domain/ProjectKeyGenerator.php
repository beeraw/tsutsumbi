<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use RuntimeException;

use function sprintf;

/**
 * Derives the ticket prefix from the directory name: uppercase, non
 * alphanumeric characters dropped, truncated. Collisions get a numeric suffix.
 */
final readonly class ProjectKeyGenerator
{
    private const string FALLBACK = 'PROJECT';
    private const int MAX_ATTEMPTS = 999;

    public function __construct(
        private ProjectRepository $projects,
        private PathNormalizer $paths,
    ) {
    }

    public function generate(string $normalizedPath): string
    {
        $base = $this->baseKey($this->paths->basename($normalizedPath));

        if (!$this->projects->keyExists($base)) {
            return $base;
        }

        for ($attempt = 2; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $suffix = (string) $attempt;
            $candidate = mb_substr($base, 0, Project::KEY_MAX_LENGTH - mb_strlen($suffix)).$suffix;

            if (!$this->projects->keyExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf('Could not derive a free project key from "%s".', $base));
    }

    private function baseKey(string $directoryName): string
    {
        $key = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($directoryName)) ?? '';

        if ('' === $key) {
            return self::FALLBACK;
        }

        return mb_substr($key, 0, Project::KEY_MAX_LENGTH);
    }
}
