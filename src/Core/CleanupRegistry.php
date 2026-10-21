<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core;

use Illuminate\Support\Collection;
use TLabsCo\LaravelCleanupData\Core\Cleaners\BaseCleaner;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanupHookContract;
use TLabsCo\LaravelCleanupData\Core\Hooks\AfterCleanupHook;
use TLabsCo\LaravelCleanupData\Core\Hooks\BeforeCleanupHook;
use TLabsCo\LaravelCleanupData\Core\Hooks\LoggingHook;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerTagsParser;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Central registry for cleanup actions
 *
 * Manages registration and discovery of all cleanup actions across packages
 */
final class CleanupRegistry
{
    /** @var array<string, string> */
    protected array $cleaners = [];

    /** @var array<string, string> */
    protected array $cleanerAliases = [];

    /** @var array<string, array<string, mixed>> */
    protected array $cleanerOptions = [];

    /** @var array<CleanupHookContract> */
    protected array $hooks = [];

    public function __construct(private readonly CleanerTagsParser $tagsParser) {}

    public function boot(): void
    {
        $this->registerConfigCleaners();
        $this->defaultHooks();
    }

    private function registerConfigCleaners(): void
    {
        /** @var array<string, array<string, mixed>> $configCleaners */
        $configCleaners = (array) config('laravel-cleanup-data.cleaners_classes', []);
        foreach ($configCleaners as $cleanerClass => $meta) {
            $configKey = $meta['config_key'] ?? null;
            $baseConfig = $configKey ? (array) config("laravel-cleanup-data.cleaners.{$configKey}", []) : [];
            $inlineOpts = (array) ($meta['options'] ?? []);
            $resolved = array_merge($baseConfig, $inlineOpts);

            $this->register($cleanerClass, $meta['alias'] ?? null, $resolved);
        }
    }

    /**
     * Register a cleaner class
     *
     * @param  string  $cleanerClass  Fully qualified class name implementing CleanerContract
     * @param  string|null  $alias  Optional alias for the cleaner
     * @param  array<string, mixed>  $resolvedOptions  Resolved options from config_key and inline options
     *
     * @throws \InvalidArgumentException If class doesn't implement CleanerContract
     */
    public function register(string $cleanerClass, ?string $alias = null, array $resolvedOptions = []): void
    {
        if (! is_subclass_of($cleanerClass, CleanerContract::class)) {
            throw new \InvalidArgumentException(
                "{$cleanerClass} must implement CleanerContract"
            );
        }

        $this->cleaners[$cleanerClass] = $cleanerClass;

        if ($alias) {
            $this->cleanerAliases[$alias] = $cleanerClass;
        }

        if (! empty($resolvedOptions)) {
            $this->cleanerOptions[$cleanerClass] = $resolvedOptions;
            if ($alias) {
                $this->cleanerOptions[$alias] = $resolvedOptions;
            }

            app()->afterResolving($cleanerClass, function (CleanerContract $cleaner) use ($resolvedOptions): void {
                if ($cleaner instanceof BaseCleaner) {
                    $cleaner->setResolvedOptions($resolvedOptions);
                }
            });
        }
    }

    /**
     * Register a cleanup hook
     *
     * @param  CleanupHookContract  $hook  Hook instance to register
     */
    public function registerHook(CleanupHookContract $hook): void
    {
        $this->hooks[] = $hook;
    }

    protected function defaultHooks(): void
    {
        foreach ([BeforeCleanupHook::class, AfterCleanupHook::class, LoggingHook::class] as $hookClass) {
            $this->registerHook(app($hookClass));
        }
    }

    public function getCleaner(string $cleanerClassOrAlias): ?CleanerContract
    {
        $class = $this->cleaners[$cleanerClassOrAlias] ?? $this->cleanerAliases[$cleanerClassOrAlias] ?? $cleanerClassOrAlias;

        if (! class_exists($class)) {
            return null;
        }

        /** @var CleanerContract $cleaner */
        $cleaner = app($class);
        $options = $this->cleanerOptions[$cleanerClassOrAlias] ?? $this->cleanerOptions[$class] ?? null;
        if ($cleaner instanceof BaseCleaner && $options !== null) {
            $cleaner->setResolvedOptions($options);
        }

        return $cleaner;
    }

    public function getHook(string $hookClass): ?CleanupHookContract
    {
        return collect($this->hooks)
            ->first(fn (CleanupHookContract $hook) => $hook::class === $hookClass);
    }

    /**
     * Execute registered hooks
     *
     * @param  string  $phase  The phase ('before' or 'after')
     * @param  CleanerContract|null  $cleaner  The cleaner being executed (null for global hooks)
     * @param  CleanupResult|null  $result  The cleanup result (only for after hooks)
     */
    public function executeHooks(string $phase, ?CleanerContract $cleaner = null, ?CleanupResult $result = null): void
    {
        foreach ($this->hooks as $hook) {
            $hook->execute($cleaner, $result);
        }
    }

    /**
     * Get all registered cleaners
     *
     * @return array<string, string> Array of aliases/class names => class names
     */
    public function all(): array
    {
        return $this->cleaners;
    }

    /**
     * Get cleaner instances (only enabled ones)
     *
     * @return Collection<int, CleanerContract> Collection of instantiated cleaners
     */
    public function instances(): Collection
    {
        /* @phpstan-ignore-next-line */
        return collect($this->cleaners)
            ->map(fn (string $class) => app($class))
            ->filter(fn (CleanerContract $cleaner) => $cleaner->isEnabled());
    }

    /**
     * Get cleaners by tag (supports single tag, multiple tags array, or string with delimiters: , ; : |)
     *
     * @param  string|array<string>  $tag  The tag(s) or delimited string to filter by
     * @return Collection<int, CleanerContract> Collection of cleaners with matching tags
     */
    public function byTag(string|array $tag): Collection
    {
        $tags = $this->tagsParser->execute($tag);

        if (empty($tags)) {
            return collect();
        }

        return $this->instances()
            ->filter(fn (CleanerContract $c) => ! empty(array_intersect($tags, $c->getTags())));
    }

    /**
     * Check if a cleaner is registered
     *
     * @param  string  $cleanerClassOrAlias  Class name or alias
     * @return bool True if registered
     */
    public function has(string $cleanerClassOrAlias): bool
    {
        return isset($this->cleaners[$cleanerClassOrAlias]) || isset($this->cleanerAliases[$cleanerClassOrAlias]);
    }
}
