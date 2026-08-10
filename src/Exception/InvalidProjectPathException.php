<?php

declare(strict_types=1);

namespace App\Exception;

use DomainException;

use function sprintf;

final class InvalidProjectPathException extends DomainException
{
    public static function empty(): self
    {
        return new self('A project path must not be empty.');
    }

    public static function notAbsolute(string $path): self
    {
        return new self(sprintf('The project path "%s" must be absolute.', $path));
    }

    public static function escapesRoot(string $path): self
    {
        return new self(sprintf('The project path "%s" climbs above the filesystem root.', $path));
    }

    public static function tooLong(string $path, int $maxLength): self
    {
        return new self(sprintf('The project path is %d characters long, above the %d allowed.', mb_strlen($path), $maxLength));
    }
}
