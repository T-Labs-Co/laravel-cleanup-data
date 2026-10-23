<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Actions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;

/** Data repair: fix, normalize, or correct field values. */
final class TransformAction implements CleanupAction
{
    /**
     * @param  array<string, Closure(mixed, Model): mixed>  $transforms  column => fixer
     * @param  bool  $quietly  true = saveQuietly(); false = save() so audit observers fire
     */
    public function __construct(
        private readonly array $transforms,
        private readonly bool $quietly = true,
    ) {}

    public function execute(Model $model): bool
    {
        $changes = [];
        foreach ($this->transforms as $column => $fixer) {
            $old = $model->getAttribute($column);
            $new = $fixer($old, $model);
            if ($new !== $old) {
                $changes[$column] = $new;
            }
        }

        if ($changes === []) {
            return false; // idempotent: nothing to write
        }

        $model->forceFill($changes);

        return $this->quietly ? $model->saveQuietly() : (bool) $model->save();
    }

    public function preview(Model $model): array
    {
        $fields = [];
        foreach ($this->transforms as $column => $fixer) {
            if ($fixer($model->getAttribute($column), $model) !== $model->getAttribute($column)) {
                $fields[$column] = 'transform';
            }
        }

        return $fields;
    }

    /**
     * @phpstan-param Builder<Model> $query
     */
    public function applyToQuery(Builder $query): void
    {
        // No query-level filtering for transform actions
    }
}
