<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Project;
use App\Exception\InvalidProjectPathException;
use App\Exception\ProjectConflictException;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies a change to an existing project, and flushes it.
 *
 * The counterpart of ProjectResolver, which owns creation and deliberately
 * never writes to a project it found. Everything that edits one goes through
 * here — the interface and the API alike — so the path stays normalised and the
 * two unique columns keep their promise wherever the change came from.
 */
final readonly class ProjectEditor
{
    public function __construct(
        private ProjectRepository $projects,
        private EntityManagerInterface $entityManager,
        private PathNormalizer $paths,
    ) {
    }

    /**
     * @throws ProjectConflictException    if the new path or key belongs to another project
     * @throws InvalidProjectPathException if the new path is not a usable absolute path
     */
    public function apply(Project $project, ProjectChange $change): void
    {
        if ($change->isEmpty()) {
            return;
        }

        // A field sent as null is treated as absent everywhere but the
        // description: a project without a name, a path or a key is not a state
        // the model has, so "name": null can only be a caller's slip.
        if ($change->has(ProjectChange::NAME) && null !== $change->name) {
            $project->setName(trim($change->name));
        }

        if ($change->has(ProjectChange::DESCRIPTION)) {
            $description = trim((string) $change->description);
            $project->setDescription('' === $description ? null : $description);
        }

        if ($change->has(ProjectChange::PATH) && null !== $change->path) {
            $project->setPath($this->freePath($project, $change->path));
        }

        if ($change->has(ProjectChange::KEY) && null !== $change->key) {
            $project->setKey($this->freeKey($project, $change->key));
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // The checks above lose to a concurrent edit taking the same value
            // between the read and the write. Rare enough to answer with the
            // same refusal rather than a retry, and the constraint is what makes
            // the guarantee anyway.
            throw ProjectConflictException::pathTaken($project->getPath());
        }
    }

    private function freePath(Project $project, string $rawPath): string
    {
        $path = $this->paths->normalize($rawPath);
        $holder = $this->projects->findOneByPath($path);

        if (null !== $holder && $holder !== $project) {
            throw ProjectConflictException::pathTaken($path);
        }

        return $path;
    }

    private function freeKey(Project $project, string $rawKey): string
    {
        $key = mb_strtoupper(trim($rawKey));
        $holder = $this->projects->findOneByKey($key);

        if (null !== $holder && $holder !== $project) {
            throw ProjectConflictException::keyTaken($key);
        }

        return $key;
    }
}
