<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Strategies;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;

final class NullStrategy implements AnonymizationStrategy
{
    public function apply(mixed $value, Model $model, string $field): mixed
    {
        return null;
    }

    public function label(): string
    {
        return 'null';
    }
}
