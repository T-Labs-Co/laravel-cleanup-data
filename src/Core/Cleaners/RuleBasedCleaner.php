<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Cleaners;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Exceptions\SafetyLimitExceeded;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

abstract class RuleBasedCleaner extends BaseCleaner
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * @return list<RetentionRule>
     */
    abstract protected function rules(): array;

    abstract protected function action(): CleanupAction;

    /**
     * Override to apply global query scopes before rules run.
     *
     * The default `::query()` call excludes soft-deleted rows (Eloquent global scope).
     * If your cleaner targets deleted records, override this method:
     *
     *   protected function scopeQuery(Builder $query): void
     *   {
     *       $query->withTrashed();   // include trashed + non-trashed
     *       // or: $query->onlyTrashed();
     *   }
     *
     * Alternatively, add a WhereRule with the same closure — scopeQuery() is just
     * a convenience for scopes that must run before any rules are applied.
     *
     * @param  Builder<Model>  $query
     */
    protected function scopeQuery(Builder $query): void
    {
        // no-op by default
    }

    /**
     * Get the config prefix for cleaner-specific options.
     * Override in subclass if the cleaner defines its own config section.
     */
    protected function getConfigPrefix(): string
    {
        return '';
    }

    /**
     * Hard cap per run. Aborts BEFORE changing anything if exceeded.
     */
    protected function maxAffected(): int
    {
        return (int) $this->config('max_affected', config('data-cleanup.retention.max_affected', 10_000));
    }

    protected function chunkSize(): int
    {
        return (int) $this->config('chunk_size', config('data-cleanup.retention.chunk_size', 500));
    }

    public function cleanup(bool $dryRun = false): CleanupResult
    {
        $query = $this->buildQuery();
        $matched = (clone $query)->count();

        if ($matched > $this->maxAffected()) {
            throw SafetyLimitExceeded::maxAffected($this->getName(), $matched, $this->maxAffected());
        }

        $action = $this->action();
        $processed = 0;
        $changed = 0;
        $sample = [];

        $query->chunkById($this->chunkSize(), function ($rows) use ($action, $dryRun, &$processed, &$changed, &$sample): void {
            foreach ($rows as $row) {
                $processed++;

                if ($dryRun) {
                    if (count($sample) < 5) {
                        // IDs and field labels only, never original values.
                        $sample[] = ['id' => $row->getKey()] + $action->preview($row);
                    }

                    continue;
                }

                if ($action->execute($row)) {
                    $changed++;
                }
            }
        });

        Log::info('cleanup run', [
            'cleaner' => $this->getName(),
            'dry_run' => $dryRun,
            'matched' => $matched,
            'changed' => $changed,
            'sample' => $sample, // empty when not dry-run
        ]);

        return CleanupResult::success(
            cleaner: $this->getName(),
            processed: $processed,
            deleted: $changed,
            message: $dryRun
                ? "Dry run: {$processed} rows would be affected. Sample: ".json_encode($sample)
                : "{$changed} of {$processed} rows affected",
        );
    }

    /**
     * @return Builder<Model>
     */
    private function buildQuery(): Builder
    {
        $query = ($this->model())::query();

        $this->scopeQuery($query);

        foreach ($this->rules() as $rule) {
            $rule->apply($query);
        }

        return $query;
    }
}
