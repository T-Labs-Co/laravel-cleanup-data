<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface CleanupAction
{
    /** Apply the change. Return true if the row was actually changed. */
    public function execute(Model $model): bool;

    /**
     * Dry-run description — field NAMES and action labels only,
     * never original values (avoids leaking PII into logs).
     *
     * @return array<string, string> e.g. ['email' => 'hashed_email']
     */
    public function preview(Model $model): array;

    /**
     * Apply query-level filtering to exclude rows that would be skipped at execution time.
     *
     * For example, AnonymizeAction can add `whereNull('anonymized_at')` so already-anonymized
     * records are excluded from the matched count and chunk iteration, improving performance
     * and providing accurate dry-run results.
     *
     * Default implementation is a no-op.
     *
     * @phpstan-param Builder<Model> $query
     */
    public function applyToQuery(Builder $query): void;
}
