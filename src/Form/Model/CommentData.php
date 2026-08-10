<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class CommentData
{
    #[Assert\NotBlank(message: 'comment.body.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: 20000, maxMessage: 'comment.body.too_long')]
    public ?string $body = null;

    public function trimmedBody(): string
    {
        return trim($this->body ?? '');
    }
}
