<?php

declare(strict_types=1);

namespace App\Security;

final readonly class ParsedApiToken
{
    public function __construct(
        public string $selector,
        public string $verifier,
    ) {
    }
}
