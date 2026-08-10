<?php

declare(strict_types=1);

namespace App\Dto;

use App\Domain\PathNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResolveProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'project.path.not_blank', normalizer: 'trim')]
        #[Assert\Length(max: PathNormalizer::MAX_LENGTH, maxMessage: 'project.path.too_long')]
        public string $path = '',
        #[Assert\Length(max: 120, maxMessage: 'project.name.too_long')]
        public ?string $name = null,
    ) {
    }
}
