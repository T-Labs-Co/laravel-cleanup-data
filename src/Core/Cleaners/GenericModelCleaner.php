<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Cleaners;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

class GenericModelCleaner extends BaseCleaner
{
    /**
     * @var array<string>
     */
    protected array $tags = ['database', 'generic', 'daily', 'model-prune'];

    public function getName(): string
    {
        return 'Generic Model Pruner';
    }

    public function getDescription(): string
    {
        return 'Prune arbitrary Eloquent models (config-driven via prune_models)';
    }

    protected function getConfigPrefix(): string
    {
        return '';
    }

    #[\Override]
    public function isEnabled(): bool
    {
        if (! (bool) $this->config('enabled', true)) {
            return false;
        }

        /** @var array<array<string, mixed>> $models */
        $models = (array) config('laravel-cleanup-data.cleaners.prune_models', []);

        return collect($models)
            ->where('enabled', true)
            ->isNotEmpty();
    }

    public function cleanup(bool $dryRun = false): CleanupResult
    {
        /** @var array<array<string, mixed>> $entries */
        $entries = (array) config('laravel-cleanup-data.cleaners.prune_models', []);
        $totalProcessed = 0;
        $totalDeleted = 0;
        $details = [];

        foreach ($entries as $entry) {
            if (empty($entry['enabled']) || empty($entry['model']) || ! is_string($entry['model']) || ! is_a($entry['model'], Model::class, true)) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $entry['model'];

            $pruner = ModelPruner::for($modelClass)
                ->column((string) ($entry['column'] ?? 'created_at'))
                ->days((int) ($entry['days'] ?? 30))
                ->force((bool) ($entry['force'] ?? false))
                ->name((string) ($entry['name'] ?? class_basename($entry['model'])));

            $result = $dryRun ? $pruner->dryRun() : $pruner->clean();

            $totalProcessed += $result->itemsProcessed;
            $totalDeleted += $result->itemsDeleted;
            if ($result->message !== null) {
                $details[] = $result->message;
            }
        }

        return CleanupResult::success(
            cleaner: $this->getName(),
            processed: $totalProcessed,
            deleted: $totalDeleted,
            message: $totalProcessed > 0 ? implode(' | ', $details) : 'Nothing to prune',
            details: ['messages' => $details],
        );
    }
}
