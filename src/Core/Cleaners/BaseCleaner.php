<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Cleaners;

use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;

/**
 * Base cleaner abstract class
 *
 * Provides common functionality for all cleaners, including:
 * - Enabled state management
 * - Tag management
 * - Configuration access with package prefix
 */
abstract class BaseCleaner implements CleanerContract
{
    /**
     * Whether this cleaner is enabled
     */
    protected bool $enabled = true;

    protected bool $absoluteConfig = false;

    /**
     * @var array<string, mixed> Inline options merged over config_key values at registration
     */
    protected array $resolvedOptions = [];

    /**
     * Tags for this cleaner
     *
     * @var array<string>
     */
    protected array $tags = [];

    /**
     * Set resolved options from registry
     *
     * @param  array<string, mixed>  $options
     */
    public function setResolvedOptions(array $options): void
    {
        $this->resolvedOptions = $options;
    }

    /**
     * Check if this cleaner is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) $this->config('enabled', $this->enabled);
    }

    /**
     * Get the cleaner tags
     *
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Get config value with package prefix
     *
     * Uses the package-specific config() helper to access configuration
     * with the prefix defined by getConfigPrefix()
     *
     * @param  string  $key  Configuration key (without prefix)
     * @param  mixed  $default  Default value if not found
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        // Inline options take priority
        if (array_key_exists($key, $this->resolvedOptions)) {
            return $this->resolvedOptions[$key];
        }

        return config($this->rootKey().$this->getConfigPrefix().'.'.$key, $default);
    }

    /**
     * Get the root key for cleaner configuration
     */
    protected function rootKey(): string
    {
        return $this->absoluteConfig ? '' : 'laravel-cleanup-data.cleaners.';
    }

    /**
     * Get the config prefix for this cleaner
     *
     * This prefix is prepended to all config() calls
     */
    abstract protected function getConfigPrefix(): string;
}
