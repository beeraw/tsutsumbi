<?php

declare(strict_types=1);

namespace App\Dto;

use App\Domain\PathNormalizer;
use App\Domain\ProjectChange;
use App\Entity\Project;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every field is optional: a PATCH says what changes, not what the project is.
 *
 * As on a ticket, which keys the caller actually sent cannot be read off this
 * object — null is a legitimate value for the description — so the controller
 * passes the payload keys alongside it.
 */
final readonly class UpdateProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'project.name.not_blank', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'project.name.too_long')]
        public ?string $name = null,
        public ?string $description = null,
        #[Assert\NotBlank(message: 'project.path.not_blank', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: PathNormalizer::MAX_LENGTH, maxMessage: 'project.path.too_long')]
        public ?string $path = null,
        #[Assert\Regex(pattern: '/^[A-Za-z0-9]{1,'.Project::KEY_MAX_LENGTH.'}$/', message: 'project.key.invalid')]
        public ?string $key = null,
    ) {
    }

    /**
     * @param list<string> $providedFields the keys present in the request body
     */
    public function toChange(array $providedFields): ProjectChange
    {
        return new ProjectChange(
            $providedFields,
            $this->name,
            $this->description,
            $this->path,
            $this->key,
        );
    }
}
