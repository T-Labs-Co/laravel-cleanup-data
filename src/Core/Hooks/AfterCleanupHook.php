<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Hooks;

use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanupHookContract;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;
use TLabsCo\LaravelCleanupData\Services\Logger;

/**
 * Hook executed after cleanup operations
 *
 * Logs the completion status of cleanup operations
 */
class AfterCleanupHook implements CleanupHookContract
{
    /**
     * Execute the hook
     */
    public function execute(?CleanerContract $cleaner = null, ?CleanupResult $result = null): void
    {
        if ($result) {
            $status = $result->success ? 'completed' : 'failed';

            Logger::getLogger()->info("After Cleanup {$status}: {$result->cleaner}", [
                'success' => $result->success,
                'processed' => $result->itemsProcessed,
                'deleted' => $result->itemsDeleted,
                'message' => $result->message,
            ]);
        } else {
            Logger::getLogger()->info('Cleanup batch completed');
        }
    }
}
