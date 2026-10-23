<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface RetentionRule
{
    /**
     * Narrow the query to rows this rule makes eligible (or excludes).
     *
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    public function apply(Builder $query): void;
}
