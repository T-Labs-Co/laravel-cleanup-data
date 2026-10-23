<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Actions;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;

final class AnonymizeAction implements CleanupAction
{
    private readonly string $markerColumn;

    /**
     * @param  array<string, AnonymizationStrategy>  $fields  column => strategy
     * @param  string|null  $markerColumn  idempotency marker; skip rows already anonymized (defaults to config data-cleanup.retention.marker_column or 'anonymized_at')
     * @param  bool  $quietly  true = saveQuietly() (suppresses observers/events);
     *                         false = save() (audit observers fire normally)
     */
    public function __construct(
        private readonly array $fields,
        ?string $markerColumn = null,
        private readonly bool $quietly = true,
    ) {
        $this->markerColumn = $markerColumn ?? (string) (config('data-cleanup.retention.marker_column') ?? config('data-cleanup.retention.anonymized_at_column', 'anonymized_at'));
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
}
