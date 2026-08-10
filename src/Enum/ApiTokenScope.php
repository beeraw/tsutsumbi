<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Scopes granted to a machine token. There is deliberately no administration
 * scope: token and user management is reachable through a human session only.
 */
enum ApiTokenScope: string
{
    case ProjectsWrite = 'projects:write';
    case TicketsWrite = 'tickets:write';
    case CommentsWrite = 'comments:write';
    case AttachmentsWrite = 'attachments:write';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }

    public function translationKey(): string
    {
        return 'api_token.scope.'.str_replace(':', '_', $this->value);
    }
}
