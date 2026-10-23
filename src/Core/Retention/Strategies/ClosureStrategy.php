<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Strategies;

use Closure;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;

final class ClosureStrategy implements AnonymizationStrategy
{
    public function __construct(
        private readonly Closure $callback,
        private readonly string $label = 'custom',
    ) {}

    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return ($this->callback)($value, $model, $field);
    }

    public function label(): string
    {
        return $this->label;
    }
}
