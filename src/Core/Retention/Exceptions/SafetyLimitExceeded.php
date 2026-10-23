<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Exceptions;

use RuntimeException;

final class SafetyLimitExceeded extends RuntimeException
{
    public static function maxAffected(string $cleaner, int $matched, int $max): self
    {
        return new self(
            "{$cleaner}: {$matched} rows matched, limit is {$max}. Aborted, nothing changed."
        );
    }
}
