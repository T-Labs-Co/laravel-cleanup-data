<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention;

use Closure;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\ClosureStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\FixedStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\HashedEmailStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\MaskStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\NullStrategy;

/**
 * Fluent factory for creating anonymization strategies.
 */
class Strategy
{
    public static function null(): NullStrategy
    {
        return new NullStrategy;
    }

    public static function fixed(mixed $replacement): FixedStrategy
    {
        return new FixedStrategy($replacement);
    }

    public static function hashedEmail(?string $domain = null): HashedEmailStrategy
    {
        return new HashedEmailStrategy($domain);
    }

    public static function mask(int $keepLast = 0, string $char = '*'): MaskStrategy
    {
        return new MaskStrategy($keepLast, $char);
    }

    public static function closure(Closure $callback): ClosureStrategy
    {
        return new ClosureStrategy($callback);
    }

    /**
     * Alias for closure()
     */
    public static function custom(Closure $callback): ClosureStrategy
    {
        return new ClosureStrategy($callback);
    }
}
