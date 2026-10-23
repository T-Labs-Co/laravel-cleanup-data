<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Rules;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;

/** Fully custom rule: any scope or closure. */
final class WhereRule implements RetentionRule
{
    public function __construct(private readonly Closure $callback) {}

    public function apply(Builder $query): void
    {
        ($this->callback)($query);
    }
}
