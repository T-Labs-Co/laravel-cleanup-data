<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Rules;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;

/**
 * Retains records matching a given condition until they exceed a retention threshold (e.g. 30 days, 7 years).
 * Target rows for cleanup: (condition is true) AND (date_column < cutoff).
 */
final class RetainUntil implements RetentionRule
{
    /**
     * @param  (Closure(Builder<*>): void)|RetentionRule|null  $condition
     */
    public function __construct(
        private readonly Closure|RetentionRule|null $condition = null,
        private readonly int $days = 0,
        private readonly int $months = 0,
        private readonly int $years = 0,
        private readonly string $column = 'created_at',
    ) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    public function apply(Builder $query): void
    {
        /** @var CarbonInterface $cutoff */
        $cutoff = now()->subDays($this->days)->subMonths($this->months)->subYears($this->years);

        $query->where(function (Builder $nestedQuery) use ($cutoff): void {
            if ($this->condition instanceof Closure) {
                ($this->condition)($nestedQuery);
            } elseif ($this->condition instanceof RetentionRule) {
                $this->condition->apply($nestedQuery);
            }

            $nestedQuery->where($this->column, '<', $cutoff);
        });
    }
}
