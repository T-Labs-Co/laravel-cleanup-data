<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use TLabsCo\LaravelCleanupData\Core\CleanupRunner;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

class RunCleanupCommand extends Command
{
    protected $signature = 'cleanup:run
        {--tag= : Run only cleaners matching the specified tag(s). Multi-tag separator: comma, colon, semicolon, pipe}
        {--cleaner= : Run specific cleaner by class name}
        {--dry-run : Show what would be cleaned without deleting}
        {--force : Skip confirmation prompts}';

    protected $description = 'Run all registered cleanup actions';

    public function handle(CleanupRunner $runner): int
    {
        $this->info('🧹 Starting cleanup process...');
        $this->newLine();

        $cleaners = $this->getCleanersToRun($runner);

        if ($cleaners->isEmpty()) {
            $this->warn('No cleaners found to run.');

            return self::FAILURE;
        }

        $this->table(
            ['Cleaner', 'Description', 'Tags'],
            $cleaners->map(fn (CleanerContract $c) => [
                $c->getName(),
                $c->getDescription(),
                implode(', ', $c->getTags()),
            ])
        );

        if (! $this->option('force') && ! $this->option('dry-run')) {
            if (! $this->confirm('Run these cleaners?', true)) {
                $this->warn('Cleanup cancelled.');

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be deleted');
            $this->newLine();
        }

        $results = $runner->run(
            cleaners: $cleaners,
            dryRun: $dryRun,
            onCleanerStart: function (CleanerContract $cleaner): void {
                $this->info("Running: {$cleaner->getName()}");
            },
            onCleanerFinish: function (CleanerContract $cleaner, CleanupResult $result) use ($dryRun): void {
                if ($result->exception !== null) {
                    $this->error("  ✗ Exception: {$result->exception->getMessage()}");
                } elseif ($result->success) {
                    $deletedCount = $dryRun ? ($result->itemsDeleted > 0 ? $result->itemsDeleted : $result->itemsProcessed) : $result->itemsDeleted;
                    $msg = $dryRun
                        ? "Would delete {$deletedCount} items"
                        : "Deleted {$result->itemsDeleted} items";
                    $this->line("  ✓ {$msg}");

                    if ($result->message) {
                        $this->line("  → {$result->message}");
                    }
                } else {
                    $this->error("  ✗ Failed: {$result->message}");

                    if ($result->message) {
                        $this->line("  → {$result->message}");
                    }
                }

                $this->newLine();
            }
        );

        $totalProcessed = (int) array_sum(array_map(fn (CleanupResult $r) => $r->itemsProcessed, $results));
        $totalDeleted = (int) array_sum(array_map(fn (CleanupResult $r) => $r->itemsDeleted, $results));

        $this->displaySummary($results, $totalProcessed, $totalDeleted, $dryRun);

        return $runner->hasFailures($results) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, CleanerContract>
     */
    protected function getCleanersToRun(CleanupRunner $runner): Collection
    {
        $cleanerOption = $this->option('cleaner');
        $cleanerClass = is_string($cleanerOption) && $cleanerOption !== '' ? $cleanerOption : null;

        if ($cleanerClass !== null && ! $runner->getRegistry()->has($cleanerClass)) {
            $this->error("Cleaner not found: {$cleanerClass}");

            return collect();
        }

        $tagOption = $this->option('tag');
        $tag = is_string($tagOption) && $tagOption !== '' ? $tagOption : null;

        return $runner->resolveCleaners(
            tag: $tag,
            cleanerClass: $cleanerClass
        );
    }

    /**
     * @param  array<CleanupResult>  $results
     */
    protected function displaySummary(array $results, int $totalProcessed, int $totalDeleted, bool $dryRun): void
    {
        $successful = collect($results)->filter(fn (CleanupResult $r) => $r->success)->count();
        $failed = collect($results)->filter(fn (CleanupResult $r) => ! $r->success)->count();

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Cleanup Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('Total cleaners: '.count($results));
        $this->line("Successful: {$successful}");
        if ($failed > 0) {
            $this->error("Failed: {$failed}");
        }
        $this->line("Items processed: {$totalProcessed}");

        if ($dryRun) {
            $wouldDelete = $totalDeleted > 0 ? $totalDeleted : $totalProcessed;
            $this->warn("Would delete: {$wouldDelete} items");
        } else {
            $this->line("Items deleted: {$totalDeleted}");
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
