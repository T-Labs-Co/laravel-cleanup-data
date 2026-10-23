<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AnonymizationStrategy
{
    public function apply(mixed $value, Model $model, string $field): mixed;

    public function label(): string;
}
