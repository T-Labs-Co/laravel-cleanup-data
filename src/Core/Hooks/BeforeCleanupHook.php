<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Hooks;

use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanupHookContract;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;
use TLabsCo\LaravelCleanupData\Services\Logger;

/**
 * Hook executed before cleanup operations
 *
 * Logs the start of cleanup operations for monitoring
 */
class BeforeCleanupHook implements CleanupHookContract
{
    /**
     * Execute the hook
     */
    public function execute(?CleanerContract $cleaner = null, ?CleanupResult $result = null): void
    {
        if ($cleaner) {
            Logger::getLogger()->info("Starting cleanup: {$cleaner->getName()}", [
                'cleaner' => $cleaner::class,
                'tags' => $cleaner->getTags(),
            ]);
        } else {
            Logger::getLogger()->info('Starting cleanup batch');
        }
    }
}
