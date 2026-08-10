<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the application/problem+json bodies every API error uses.
 */
final readonly class ProblemJsonResponseFactory
{
    public const string CONTENT_TYPE = 'application/problem+json';
    public const string TRANSLATION_DOMAIN = 'api';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param string|null                 $detail    a literal, for technical text such as an exception message
     * @param string|null                 $detailKey a translation key, for anything a reader is meant to understand
     * @param array<string, list<string>> $errors    field name to messages, for validation failures
     * @param array<string, string|int>   $headers
     */
    public function create(
        int $status,
        string $type,
        string $titleKey,
        ?string $detail = null,
        ?string $detailKey = null,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'type' => $type,
            'title' => $this->translator->trans($titleKey, [], self::TRANSLATION_DOMAIN),
            'status' => $status,
        ];

        $detail ??= null === $detailKey
            ? null
            : $this->translator->trans($detailKey, [], self::TRANSLATION_DOMAIN);

        if (null !== $detail) {
            $payload['detail'] = $detail;
        }

        if ([] !== $errors) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse(
            $payload,
            $status,
            $headers + ['Content-Type' => self::CONTENT_TYPE],
        );
    }
}
