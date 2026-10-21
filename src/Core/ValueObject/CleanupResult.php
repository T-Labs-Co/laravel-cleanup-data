<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\ValueObject;

/**
 * Data Transfer Object for cleanup results
 *
 * Immutable value object that represents the result of a cleanup operation
 */
class CleanupResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $cleaner,
        public readonly bool $success,
        public readonly int $itemsProcessed,
        public readonly int $itemsDeleted,
        public readonly ?string $message = null,
        public readonly array $details = [],
        public readonly ?\Throwable $exception = null,
    ) {}

    /**
     * Create a successful cleanup result
     *
     * @param  string  $cleaner  Name of the cleaner
     * @param  int  $processed  Number of items processed
     * @param  int  $deleted  Number of items deleted
     * @param  string|null  $message  Optional success message
     * @param  array<string, mixed>  $details  Optional additional details
     */
    public static function success(
        string $cleaner,
        int $processed,
        int $deleted,
        ?string $message = null,
        array $details = []
    ): self {
        return new self(
            cleaner: $cleaner,
            success: true,
            itemsProcessed: $processed,
            itemsDeleted: $deleted,
            message: $message,
            details: $details
        );
    }

    /**
     * Create a failed cleanup result
     *
     * @param  string  $cleaner  Name of the cleaner
     * @param  \Throwable  $exception  The exception that caused the failure
     * @param  string|null  $message  Optional failure message (defaults to exception message)
     */
    public static function failure(
        string $cleaner,
        \Throwable $exception,
        ?string $message = null
    ): self {
        // Send exception to CrashGuard or other error reporting service
        if (function_exists('app') && app()->bound('config') && config('laravel-cleanup-data.failure_report', true)) {
            if (function_exists('report')) {
                report($exception);
            }
        }

        return new self(
            cleaner: $cleaner,
            success: false,
            itemsProcessed: 0,
            itemsDeleted: 0,
            message: $message ?? $exception->getMessage(),
            exception: $exception
        );
    }

    /**
     * Create a skipped cleanup result
     *
     * @param  string  $cleaner  Name of the cleaner
     * @param  string  $reason  Reason why the cleanup was skipped
     */
    public static function skipped(
        string $cleaner,
        string $reason
    ): self {
        return new self(
            cleaner: $cleaner,
            success: true,
            itemsProcessed: 0,
            itemsDeleted: 0,
            message: "Skipped: {$reason}"
        );
    }
}
