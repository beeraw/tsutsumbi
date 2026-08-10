<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Project;

final readonly class ProjectResolution
{
    public function __construct(
        public Project $project,
        public bool $created,
    ) {
    }
}
