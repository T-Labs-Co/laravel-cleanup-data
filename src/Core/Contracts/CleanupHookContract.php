<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Contracts;

use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Interface for cleanup hooks
 *
 * Hooks are executed at various points during the cleanup process:
 * - Before the entire cleanup batch
 * - Before each individual cleaner
 * - After each individual cleaner (with result)
 * - After the entire cleanup batch
 */
interface CleanupHookContract
{
    /**
     * Execute the hook
     *
     * @param  CleanerContract|null  $cleaner  The cleaner being executed (null for global hooks)
     * @param  CleanupResult|null  $result  The cleanup result (only for after hooks)
     */
    public function execute(?CleanerContract $cleaner = null, ?CleanupResult $result = null): void;
}
