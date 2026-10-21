<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core;

use Illuminate\Support\Collection;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerResolver;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Runner service to execute cleanup actions and coordinate hooks.
 */
final readonly class CleanupRunner
{
    public function __construct(
        protected CleanupRegistry $registry,
        protected CleanerResolver $resolver,
    ) {}

    public static function make(): static
    {
        return app(self::class);
    }

    public function getRegistry(): CleanupRegistry
    {
        return $this->registry;
    }

    public function getResolver(): CleanerResolver
    {
        return $this->resolver;
    }

    /**
     * Resolve cleaners to run based on tag or specific cleaner class/alias.
     *
     * @param  string|array<string>|null  $tag
     * @return Collection<int, CleanerContract>
     */
    public function resolveCleaners(string|array|null $tag = null, ?string $cleanerClass = null): Collection
    {
        return $this->resolver->resolve($tag, $cleanerClass);
    }

    /**
     * Run a batch of cleaners.
     *
     * @param  iterable<CleanerContract>  $cleaners
     * @param  (callable(CleanerContract): void)|null  $onCleanerStart
     * @param  (callable(CleanerContract, CleanupResult): void)|null  $onCleanerFinish
     * @return array<CleanupResult>
     */
    public function run(
        iterable $cleaners,
        bool $dryRun = false,
        ?callable $onCleanerStart = null,
        ?callable $onCleanerFinish = null,
    ): array {
        // Execute before batch hook
        $this->registry->executeHooks('before');

        $results = [];

        foreach ($cleaners as $cleaner) {
            if ($onCleanerStart !== null) {
                $onCleanerStart($cleaner);
            }

            $result = $this->runCleaner($cleaner, $dryRun);
            $results[] = $result;

            if ($onCleanerFinish !== null) {
                $onCleanerFinish($cleaner, $result);
            }
        }

        // Execute after batch hook
        $this->registry->executeHooks('after');

        return $results;
    }

    /**
     * Execute a single cleaner with its lifecycle hooks and exception handling.
     */
    private function runCleaner(CleanerContract $cleaner, bool $dryRun = false): CleanupResult
    {
        // Execute before cleaner hook
        $this->registry->executeHooks('before', $cleaner);

        try {
            $result = $cleaner->cleanup($dryRun);
        } catch (\Throwable $e) {
            $result = CleanupResult::failure(
                $cleaner->getName(),
                $e
            );
        }

        // Execute after cleaner hook
        $this->registry->executeHooks('after', $cleaner, $result);

        return $result;
    }

    /**
     * Determine if any cleanup results failed.
     *
     * @param  array<CleanupResult>  $results
     */
    public function hasFailures(array $results): bool
    {
        return collect($results)->contains(fn (CleanupResult $r) => ! $r->success);
    }
}
