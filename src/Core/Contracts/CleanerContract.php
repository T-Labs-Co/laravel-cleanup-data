<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Contracts;

use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Interface for cleanup actions
 *
 * Implement this interface to create a cleaner that can be registered
 * with the CleanupRegistry and executed by the cleanup:run command.
 */
interface CleanerContract
{
    /**
     * Get the cleaner name for display
     *
     * @return string Human-readable name
     */
    public function getName(): string;

    /**
     * Get the cleaner description
     *
     * @return string Description of what this cleaner does
     */
    public function getDescription(): string;

    /**
     * Execute the cleanup action
     *
     * @param  bool  $dryRun  If true, show what would be deleted without actually deleting
     * @return CleanupResult Result of the cleanup operation
     */
    public function cleanup(bool $dryRun = false): CleanupResult;

    /**
     * Check if this cleaner is enabled
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool;

    /**
     * Get the cleaner tags for categorization
     *
     * Tags allow filtering cleaners by category (e.g., 'daily', 'logs', 'auth')
     *
     * @return array<string> Array of tags
     */
    public function getTags(): array;
}
