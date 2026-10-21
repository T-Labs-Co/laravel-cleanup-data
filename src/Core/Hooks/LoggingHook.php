<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Hooks;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanupHookContract;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;
use TLabsCo\LaravelCleanupData\Services\Logger;

/**
 * Detailed logging hook for cleanup operations
 *
 * Provides comprehensive logging with context and exception details
 */
class LoggingHook implements CleanupHookContract
{
    private LoggerInterface $logger;

    /**
     * Create a new logging hook
     *
     * @param  string  $level  Log level (default: info)
     */
    public function __construct(
        protected string $level = 'info'
    ) {
        $this->logger = Logger::getLogger();
    }

    /**
     * Execute the hook
     */
    public function execute(?CleanerContract $cleaner = null, ?CleanupResult $result = null): void
    {
        if (! $result) {
            return;
        }

        $context = [
            'cleaner' => $result->cleaner,
            'success' => $result->success,
            'items_processed' => $result->itemsProcessed,
            'items_deleted' => $result->itemsDeleted,
            'details' => $result->details,
        ];

        if ($result->exception) {
            $context['exception'] = [
                'message' => $result->exception->getMessage(),
                'file' => $result->exception->getFile(),
                'line' => $result->exception->getLine(),
                'trace' => $result->exception->getTraceAsString(),
            ];
        }

        $this->logger->log($this->level, "Cleanup: {$result->message}", $context);
    }
}
