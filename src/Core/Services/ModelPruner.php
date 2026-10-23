<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Action;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\AnonymizeAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\DeleteAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\NullifyAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\TransformAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Exceptions\SafetyLimitExceeded;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\ExceptRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\OlderThanRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\WhereRule;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Fluent builder for a single-model prune or retention cleanup operation.
 *
 * @template TModel of Model
 */
class ModelPruner
{
    private string $column = 'created_at';

    private ?int $days = null;

    private bool $force = false;

    private string $name;

    /**
     * @var list<RetentionRule>
     */
    private array $rules = [];

    /**
     * @var list<Closure(Builder<TModel>): void>
     */
    private array $scopes = [];

    private ?CleanupAction $action = null;

    private ?int $maxAffected = null;

    private ?int $chunkSize = null;

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
     * Add a retention rule.
     *
     * @return self<TModel>
     */
    public function rule(RetentionRule $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    /**
     * Add an olderThan rule.
     *
     * @return self<TModel>
     */
    public function olderThan(int $days = 0, int $months = 0, int $years = 0, ?string $column = null): self
    {
        $this->rules[] = new OlderThanRule(
            column: $column ?? $this->column,
            days: $days,
            months: $months,
            years: $years,
        );

        return $this;
    }

    /**
     * Add a WHERE condition or closure rule.
     *
     * @param  Closure(Builder<TModel>): void|string|array<string, mixed>  $column
     * @return self<TModel>
     */
    public function where(Closure|string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if ($column instanceof Closure) {
            $this->rules[] = new WhereRule($column);
        } else {
            $this->rules[] = new WhereRule(function (Builder $query) use ($column, $operator, $value, $boolean): void {
                if (is_array($column)) {
                    $query->where($column, null, null, $boolean);
                } elseif (func_num_args() === 2) {
                    $query->where($column, '=', $operator, $boolean);
                } else {
                    $query->where($column, $operator, $value, $boolean);
                }
            });
        }

        return $this;
    }

    /**
     * Add an EXCEPT / WHERE NOT rule.
     *
     * @param  Closure(Builder<TModel>): void|RetentionRule  $rule
     * @return self<TModel>
     */
    public function except(Closure|RetentionRule $rule): self
    {
        $inner = $rule instanceof RetentionRule ? $rule : new WhereRule($rule);
        $this->rules[] = new ExceptRule($inner);

        return $this;
    }

    /**
     * Add global/custom query scope callback (e.g. $q->withTrashed() or $q->onlyTrashed()).
     *
     * @param  Closure(Builder<TModel>): void  $callback
     * @return self<TModel>
     */
    public function scope(Closure $callback): self
    {
        $this->scopes[] = $callback;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function withTrashed(): self
    {
        return $this->scope(function (Builder $query): void {
            if (method_exists($query, 'withTrashed')) {
                $query->withTrashed();
            }
        });
    }

    /**
     * @return self<TModel>
     */
    public function onlyTrashed(): self
    {
        return $this->scope(function (Builder $query): void {
            if (method_exists($query, 'onlyTrashed')) {
                $query->onlyTrashed();
            }
        });
    }

    /**
     * Set cleanup action.
     *
     * @return self<TModel>
     */
    public function action(CleanupAction|Action|string $action): self
    {
        if ($action instanceof CleanupAction) {
            $this->action = $action;
        } elseif ($action instanceof Action) {
            $this->action = $action->toCleanupAction();
        } elseif (is_string($action)) {
            $this->action = match ($action) {
                'force_delete', 'forceDelete' => new DeleteAction(true),
                default => new DeleteAction(false),
            };
        }

        return $this;
    }

    /**
     * Configure anonymize action.
     *
     * @param  array<string, AnonymizationStrategy>  $fields
     * @return self<TModel>
     */
    public function anonymize(array $fields, ?string $markerColumn = null, bool $quietly = true): self
    {
        $this->action = new AnonymizeAction($fields, $markerColumn, $quietly);

        return $this;
    }

    /**
     * Configure nullify action.
     *
     * @param  list<string>  $columns
     * @return self<TModel>
     */
    public function nullify(array $columns, bool $quietly = true): self
    {
        $this->action = new NullifyAction($columns, $quietly);

        return $this;
    }

    /**
     * Configure transform action.
     *
     * @param  array<string, Closure(mixed, Model): mixed>  $transforms
     * @return self<TModel>
     */
    public function transform(array $transforms, bool $quietly = true): self
    {
        $this->action = new TransformAction($transforms, $quietly);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function maxAffected(int $max): self
    {
        $this->maxAffected = $max;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function chunkSize(int $size): self
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Execute the prune or retention action and return a result.
     */
    public function clean(): CleanupResult
    {
        return $this->execute(dryRun: false);
    }

    /**
     * Preview what would be affected without touching data.
     */
    public function dryRun(): CleanupResult
    {
        return $this->execute(dryRun: true);
    }

    /**
     * @return Builder<TModel>
     */
    public function buildQuery(): Builder
    {
        $query = ($this->modelClass)::query();

        foreach ($this->scopes as $scope) {
            $scope($query);
        }

        if ($this->rules !== []) {
            foreach ($this->rules as $rule) {
                $rule->apply($query);
            }
        } elseif ($this->days !== null) {
            $query->where($this->column, '<', now()->subDays($this->days));
        }

        return $query;
    }

    private function execute(bool $dryRun): CleanupResult
    {
        try {
            $query = $this->buildQuery();
            $maxLimit = $this->maxAffected ?? (int) config('laravel-cleanup-data.retention.max_affected', 10_000);
            $chunkLimit = $this->chunkSize ?? (int) config('laravel-cleanup-data.retention.chunk_size', 500);

            $matched = (clone $query)->count();

            if ($matched > $maxLimit) {
                throw SafetyLimitExceeded::maxAffected($this->name, $matched, $maxLimit);
            }

            // If a specific CleanupAction was defined or rules are present with non-standard delete
            $action = $this->action ?? new DeleteAction($this->force);

            $processed = 0;
            $changed = 0;
            $sample = [];

            // If action is standard delete and no custom rules were set (or simple delete execution requested),
            // optimize when possible or iterate in chunks.
            // Using chunkById ensures consistent memory and hook execution.
            $query->chunkById($chunkLimit, function ($rows) use ($action, $dryRun, &$processed, &$changed, &$sample): void {
                foreach ($rows as $row) {
                    $processed++;

                    if ($dryRun) {
                        if (count($sample) < 5) {
                            $sample[] = ['id' => $row->getKey()] + $action->preview($row);
                        }

                        continue;
                    }

                    if ($action->execute($row)) {
                        $changed++;
                    }
                }
            });

            $daysDesc = $this->days !== null ? " older than {$this->days} days" : '';
            $actionDesc = $dryRun ? 'Dry run' : 'Processed';

            return CleanupResult::success(
                cleaner: $this->name,
                processed: $processed,
                deleted: $changed,
                message: $dryRun
                    ? sprintf('Dry run: %d rows would be affected from %s%s. Sample: %s', $processed, $this->modelClass, $daysDesc, json_encode($sample))
                    : sprintf('%s: %d of %d rows affected from %s%s', $actionDesc, $changed, $processed, $this->modelClass, $daysDesc),
            );
        } catch (\Throwable $e) {
            return CleanupResult::failure(
                cleaner: $this->name,
                exception: $e,
            );
        }
    }

    /**
     * Expose state for callers to inspect.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->modelClass,
            'column' => $this->column,
            'days' => $this->days ?? 30,
            'force' => $this->force,
            'enabled' => true,
        ];
    }
}
