<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;

final class AnonymizeAction implements CleanupAction
{
    private readonly string $markerColumn;

    private readonly bool $skipAnonymizedInQuery;

    /**
     * @param  array<string, AnonymizationStrategy>  $fields  column => strategy
     * @param  string|null  $markerColumn  idempotency marker; skip rows already anonymized (defaults to config laravel-cleanup-data.retention.marker_column or 'anonymized_at')
     * @param  bool  $quietly  true = saveQuietly() (suppresses observers/events);
     *                         false = save() (audit observers fire normally)
     * @param  bool|null  $skipAnonymizedInQuery  exclude already-anonymized rows from the query
     *                                            (defaults to config laravel-cleanup-data.retention.skip_anonymized_in_query, true)
     */
    public function __construct(
        private readonly array $fields,
        ?string $markerColumn = null,
        private readonly bool $quietly = true,
        ?bool $skipAnonymizedInQuery = null,
    ) {
        $this->markerColumn = $markerColumn ?? (string) config('laravel-cleanup-data.retention.marker_column', 'anonymized_at');
        $this->skipAnonymizedInQuery = $skipAnonymizedInQuery ?? (bool) config('laravel-cleanup-data.retention.skip_anonymized_in_query', true);
    }

    public function execute(Model $model): bool
    {
        if ($model->getAttribute($this->markerColumn) !== null) {
            return false; // already anonymized
        }

        $changes = [];
        foreach ($this->fields as $field => $strategy) {
            $changes[$field] = $strategy->apply($model->getAttribute($field), $model, $field);
        }
        $changes[$this->markerColumn] = now();

        $model->forceFill($changes);

        return $this->quietly ? $model->saveQuietly() : (bool) $model->save();
    }

    public function preview(Model $model): array
    {
        if ($model->getAttribute($this->markerColumn) !== null) {
            return ['*' => 'skip (already anonymized)'];
        }

        return array_map(fn (AnonymizationStrategy $s): string => $s->label(), $this->fields);
    }

    /**
     * @phpstan-param Builder<Model> $query
     */
    public function applyToQuery(Builder $query): void
    {
        if ($this->skipAnonymizedInQuery) {
            $query->whereNull($this->markerColumn);
        }
    }
}
