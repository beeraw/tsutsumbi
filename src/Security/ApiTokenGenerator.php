<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiToken;

use function count;
use function sprintf;
use function strlen;

use const PASSWORD_DEFAULT;

/**
 * Mints and parses tokens shaped as tsu_{selector}_{verifier}.
 *
 * Splitting the secret in two is what keeps the lookup to a single indexed
 * query while still storing nothing reusable: the selector finds the row, the
 * verifier is only ever compared against a hash.
 */
final readonly class ApiTokenGenerator
{
    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Largest multiple of 62 that fits in a byte. Bytes at or above it are
     * discarded rather than folded, which would favour the first two characters
     * of the alphabet.
     */
    private const int REJECTION_THRESHOLD = 248;

    public function generate(): GeneratedApiToken
    {
        $selector = $this->randomString(ApiToken::SELECTOR_LENGTH);
        $verifier = $this->randomString(ApiToken::VERIFIER_LENGTH);

        return new GeneratedApiToken(
            sprintf('%s_%s_%s', ApiToken::PREFIX, $selector, $verifier),
            $selector,
            $this->hash($verifier),
        );
    }

    public function hash(string $verifier): string
    {
        return password_hash($verifier, PASSWORD_DEFAULT);
    }

    public function parse(string $plain): ?ParsedApiToken
    {
        $parts = explode('_', trim($plain));

        if (3 !== count($parts)) {
            return null;
        }

        [$prefix, $selector, $verifier] = $parts;

        if (ApiToken::PREFIX !== $prefix) {
            return null;
        }

        if (ApiToken::SELECTOR_LENGTH !== strlen($selector) || ApiToken::VERIFIER_LENGTH !== strlen($verifier)) {
            return null;
        }

        if (1 !== preg_match('/^[0-9A-Za-z]+$/', $selector.$verifier)) {
            return null;
        }

        return new ParsedApiToken($selector, $verifier);
    }

    private function randomString(int $length): string
    {
        $result = '';

        while (strlen($result) < $length) {
            /** @var array<int, int> $bytes */
            $bytes = unpack('C*', random_bytes($length)) ?: [];

            foreach ($bytes as $byte) {
                if ($byte >= self::REJECTION_THRESHOLD) {
                    continue;
                }

                $result .= self::ALPHABET[$byte % 62];

                if (strlen($result) === $length) {
                    break;
                }
            }
        }

        return $result;
    }
}
