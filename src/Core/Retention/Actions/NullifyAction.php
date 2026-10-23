<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;

final class NullifyAction implements CleanupAction
{
    /**
     * @param  list<string>  $columns
     * @param  bool  $quietly  true = saveQuietly(); false = save() so audit observers fire
     */
    public function __construct(
        private readonly array $columns,
        private readonly bool $quietly = true,
    ) {}

    public function execute(Model $model): bool
    {
        $model->forceFill(array_fill_keys($this->columns, null));

        return $this->quietly ? $model->saveQuietly() : (bool) $model->save();
    }

    public function preview(Model $model): array
    {
        return array_fill_keys($this->columns, 'null');
    }

    /**
     * @phpstan-param Builder<Model> $query
     */
    public function applyToQuery(Builder $query): void
    {
        // No query-level filtering for nullify actions
    }
}
