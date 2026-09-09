<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Domain\PathNormalizer;
use App\Domain\ProjectChange;
use App\Entity\Project;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mutable on purpose: this is what the form writes into, unlike the readonly
 * DTO describing a request that already arrived.
 *
 * The form always submits its four fields, so there is no "provided" subtlety
 * here: what comes back is the whole project as the reader left it.
 */
final class ProjectEditData
{
    #[Assert\NotBlank(message: 'project.name.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 120, maxMessage: 'project.name.too_long')]
    public ?string $name = null;

    public ?string $description = null;

    #[Assert\NotBlank(message: 'project.path.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: PathNormalizer::MAX_LENGTH, maxMessage: 'project.path.too_long')]
    public ?string $path = null;

    #[Assert\Regex(pattern: '/^[A-Za-z0-9]{1,'.Project::KEY_MAX_LENGTH.'}$/', message: 'project.key.invalid')]
    public ?string $key = null;

    public static function fromProject(Project $project): self
    {
        $data = new self();
        $data->name = $project->getName();
        $data->description = $project->getDescription();
        $data->path = $project->getPath();
        $data->key = $project->getKey();

        return $data;
    }

    public function toChange(): ProjectChange
    {
        return new ProjectChange(
            [ProjectChange::NAME, ProjectChange::DESCRIPTION, ProjectChange::PATH, ProjectChange::KEY],
            name: $this->name,
            description: $this->description,
            path: $this->path,
            key: $this->key,
        );
    }
}
