<?php

declare(strict_types=1);

namespace App\Security;

/**
 * A freshly minted token. The plain form exists only here, on its way to being
 * shown once in the interface; the database keeps the selector and the hash.
 */
final readonly class GeneratedApiToken
{
    public function __construct(
        public string $plain,
        public string $selector,
        public string $verifierHash,
    ) {
    }
}
