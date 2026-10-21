<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Supports;

use Illuminate\Support\Collection;
use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;

/**
 * Resolver service to determine which cleaners to run based on tag or specific cleaner class/alias.
 */
final readonly class CleanerResolver
{
    public function __construct(
        private CleanupRegistry $registry
    ) {}

    public static function make(): static
    {
        return app(self::class);
    }

    /**
     * Resolve cleaners to run based on tag or specific cleaner class/alias.
     *
     * @param  string|array<string>|null  $tag
     * @return Collection<int, CleanerContract>
     */
    public function resolve(string|array|null $tag = null, ?string $cleanerClass = null): Collection
    {
        if ($tag !== null && $tag !== '') {
            return $this->registry->byTag($tag);
        }

        if ($cleanerClass !== null && $cleanerClass !== '') {
            if (! $this->registry->has($cleanerClass)) {
                return collect();
            }

            $cleaner = $this->registry->getCleaner($cleanerClass);

            return $cleaner ? collect([$cleaner]) : collect();
        }

        return $this->registry->instances();
    }
}
