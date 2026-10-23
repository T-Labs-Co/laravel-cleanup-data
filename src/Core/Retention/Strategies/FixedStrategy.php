<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Strategies;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;

final class FixedStrategy implements AnonymizationStrategy
{
    public function __construct(private readonly mixed $replacement) {}

    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return $this->replacement;
    }

    public function label(): string
    {
        return 'fixed';
    }
}
