<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

use function sprintf;

/**
 * The pivot of the whole system: given a path, hand back the project, creating
 * it if it does not exist yet.
 *
 * Idempotence is the contract. Two calls with the same path return the same
 * project, and a name supplied by a later call never overwrites the current one
 * — otherwise an agent replaying its session start would undo a rename made
 * from the interface.
 */
final readonly class ProjectResolver
{
    public function __construct(
        private ProjectRepository $projects,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $registry,
        private PathNormalizer $paths,
        private ProjectKeyGenerator $keys,
    ) {
    }

    public function resolve(string $rawPath, ?string $name = null): ProjectResolution
    {
        $path = $this->paths->normalize($rawPath);

        $existing = $this->projects->findOneByPath($path);
        if (null !== $existing) {
            return new ProjectResolution($existing, false);
        }

        $project = new Project(
            $this->keys->generate($path),
            $this->projectName($name, $path),
            $path,
        );

        try {
            $this->entityManager->persist($project);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // A concurrent resolve inserted the same path first. The failed
            // flush closed this manager, so start from a fresh one and read the
            // winner back: the caller must still get the project, not an error.
            return new ProjectResolution($this->reloadAfterRace($path), false);
        }

        return new ProjectResolution($project, true);
    }

    private function projectName(?string $name, string $normalizedPath): string
    {
        $name = null === $name ? '' : trim($name);

        if ('' !== $name) {
            return mb_substr($name, 0, 120);
        }

        $basename = $this->paths->basename($normalizedPath);

        return '' === $basename ? $normalizedPath : $basename;
    }

    private function reloadAfterRace(string $path): Project
    {
        $this->registry->resetManager();

        $project = $this->registry
            ->getManagerForClass(Project::class)
            ?->getRepository(Project::class)
            ->findOneBy(['path' => $path])
        ;

        if (!$project instanceof Project) {
            throw new RuntimeException(sprintf('Lost the race to create project "%s", yet it cannot be read back.', $path));
        }

        return $project;
    }
}
