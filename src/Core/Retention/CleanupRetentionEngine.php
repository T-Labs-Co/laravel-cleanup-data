<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

class CleanupRetentionEngine
{
    /**
     * Start a fluent retention / prune builder for the given model.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return ModelPruner<TModel>
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
            fn (ModelPruner $p): CleanupResult => $dryRun ? $p->dryRun() : $p->clean(),
            $pruners,
        );
    }
}
