<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Rules;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;

final class OlderThanRule implements RetentionRule
{
    public function __construct(
        private readonly string $column = 'created_at',
        private readonly int $days = 0,
        private readonly int $months = 0,
        private readonly int $years = 0,
    ) {}

    public function apply(Builder $query): void
    {
        /** @var CarbonInterface $cutoff */
        $cutoff = now()->subDays($this->days)->subMonths($this->months)->subYears($this->years);

        $query->where($this->column, '<', $cutoff);
    }
}
