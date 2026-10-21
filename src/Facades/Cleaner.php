<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Facades;

use Illuminate\Support\Facades\Facade;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPrunerFactory;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * @method static ModelPruner<\Illuminate\Database\Eloquent\Model> model(string $modelClass)
 * @method static array<CleanupResult> batch(array<mixed> $pruners, bool $dryRun = false)
 *
 * @see ModelPrunerFactory
 */
class Cleaner extends Facade
{
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return ModelPrunerFactory::class;
    }
}
