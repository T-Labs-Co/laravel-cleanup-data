<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Services;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Fluent builder for a single-model prune operation.
 *
 * @template TModel of Model
 */
class ModelPruner
{
    private string $column = 'created_at';

    private int $days = 30;

    private bool $force = false;

    private string $name;

    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(private readonly string $modelClass)
    {
        $this->name = class_basename($modelClass);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @return self<T>
     */
    public static function for(string $modelClass): self
    {
        return new self($modelClass);
    }

    /**
     * @return self<TModel>
     */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function days(int $days): self
    {
        $this->days = $days;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function force(bool $force = true): self
    {
        $this->force = $force;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Execute the prune and return a result.
     */
    public function clean(): CleanupResult
    {
        return $this->execute(dryRun: false);
    }

    /**
     * Preview what would be deleted without touching data.
     */
    public function dryRun(): CleanupResult
    {
        return $this->execute(dryRun: true);
    }

    private function execute(bool $dryRun): CleanupResult
    {
        try {
            $query = ($this->modelClass)::query()
                ->where($this->column, '<', now()->subDays($this->days));

            $count = $query->count();

            if (! $dryRun && $count > 0) {
                $this->force ? $query->forceDelete() : $query->delete();
            }

            return CleanupResult::success(
                cleaner: $this->name,
                processed: $count,
                deleted: $dryRun ? 0 : $count,
                message: sprintf(
                    '%s %d records older than %d days from %s.%s',
                    $dryRun ? 'Would delete' : 'Deleted',
                    $count,
                    $this->days,
                    $this->modelClass,
                    $this->column,
                ),
            );
        } catch (\Throwable $e) {
            return CleanupResult::failure(
                cleaner: $this->name,
                exception: $e,
            );
        }
    }

    /**
     * Expose state for GenericModelCleaner or callers to inspect.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->modelClass,
            'column' => $this->column,
            'days' => $this->days,
            'force' => $this->force,
            'enabled' => true,
        ];
    }
}
