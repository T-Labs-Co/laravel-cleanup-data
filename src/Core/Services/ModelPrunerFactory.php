<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Services;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

class ModelPrunerFactory
{
    /**
     * Start a fluent prune builder for the given model.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @return ModelPruner<T>
     */
    public function model(string $modelClass): ModelPruner
    {
        return ModelPruner::for($modelClass);
    }

    /**
     * Run a batch of ModelPruner instances and aggregate results.
     *
     * @param  ModelPruner<Model>[]  $pruners
     * @return CleanupResult[]
     */
    public function batch(array $pruners, bool $dryRun = false): array
    {
        return array_map(
            fn (ModelPruner $p) => $dryRun ? $p->dryRun() : $p->clean(),
            $pruners,
        );
    }
}
