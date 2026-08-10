<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommentRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'comment.body.not_blank', normalizer: 'trim')]
        #[Assert\Length(max: 20000, maxMessage: 'comment.body.too_long')]
        public string $body = '',
    ) {
    }
}
