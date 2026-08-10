<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The "type" member of an RFC 7807 payload: a stable identifier a client can
 * branch on, unlike the human-readable title.
 */
final class ProblemType
{
    public const string UNAUTHORIZED = 'urn:tsutsumbi:unauthorized';
    public const string FORBIDDEN = 'urn:tsutsumbi:forbidden';
    public const string NOT_FOUND = 'urn:tsutsumbi:not-found';
    public const string MALFORMED_REQUEST = 'urn:tsutsumbi:malformed-request';
    public const string VALIDATION_FAILED = 'urn:tsutsumbi:validation-failed';
    public const string INVALID_TICKET_STATE = 'urn:tsutsumbi:invalid-ticket-state';
    public const string INVALID_PROJECT_PATH = 'urn:tsutsumbi:invalid-project-path';
    public const string ATTACHMENT_REJECTED = 'urn:tsutsumbi:attachment-rejected';
    public const string RATE_LIMITED = 'urn:tsutsumbi:rate-limited';
    public const string INTERNAL_ERROR = 'urn:tsutsumbi:internal-error';

    private function __construct()
    {
    }
}
