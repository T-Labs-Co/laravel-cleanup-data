<?php

use TLabsCo\LaravelCleanupData\Core\Cleaners\ExpiredFilesCleaner;
use TLabsCo\LaravelCleanupData\Core\Cleaners\GenericModelCleaner;

return [

    /*
    |--------------------------------------------------------------------------
    | Cleanup System Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the centralized
    | cleanup system. Each cleaner can be enabled/disabled and configured
    | independently.
    |
    */

    /**
     * Enable/disable the entire cleanup system
     */
    'enabled' => (bool) env('DATA_CLEANUP_ENABLED', true),

    'failure_report' => (bool) env('DATA_CLEANUP_FAILURE_REPORT', true),

    'cleaners' => [
        /**
         * Generic Model Pruner Configuration
         */
        'prune_models' => [
            // [
            //     'name'    => 'activity-logs',
            //     'model'   => App\Models\ActivityLog::class,
            //     'column'  => 'created_at',
            //     'days'    => 30,
            //     'force'   => false,
            //     'enabled' => true,
            // ],
        ],

        /**
         * Expired Files Cleaner Configuration
         */
        'expired_files' => [
            'enabled' => (bool) env('DATA_CLEANUP_EXPIRED_FILES_ENABLED', false),
            'disk' => env('DATA_CLEANUP_STORAGE_DISK', 'local'),

            /**
             * Folders to clean (relative to storage_path())
             * Can be a comma-separated string or array
             */
            'folders' => env('DATA_CLEANUP_STORAGE_FOLDERS', 'temp,exports,tmp'),

            /**
             * Number of days before a file is considered expired
             */
            'expired_in_days' => (int) env('DATA_CLEANUP_STORAGE_EXPIRED_DAYS', 30),
        ],
    ],

    /**
     * Hook Configuration
     */
    'hooks' => [
        /**
         * Enable detailed logging via LoggingHook
         */
        'logging_enabled' => (bool) env('DATA_CLEANUP_LOGGING_ENABLED', true),

        /**
         * Log level for cleanup operations
         * Options: debug, info, notice, warning, error, critical, alert, emergency
         */
        'log_level' => env('DATA_CLEANUP_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | Configure automatic scheduling of the cleanup:run command.
    | Set enabled=false to manage scheduling yourself in routes/console.php
    | or app/Console/Kernel.php.
    |
    | Default to run at 2am every day
    */
    'schedule' => [
        'enabled' => (bool) env('DATA_CLEANUP_SCHEDULE_ENABLED', false),
        'expression' => env('DATA_CLEANUP_SCHEDULE_EXPRESSION', '0 2 * * *'),
        'without_overlapping' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Channel Configuration
    | Merged into Laravel's logging config as the 'cleanup' channel.
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'driver' => 'daily',
        'path' => storage_path('logs/cleanup.log'),
        'level' => env('DATA_CLEANUP_LOG_LEVEL', 'debug'),
        'days' => 7,
    ],

    'cleaners_classes' => [
        GenericModelCleaner::class => [
            'alias' => 'generic-models',
            'config_key' => null,
        ],
        ExpiredFilesCleaner::class => [
            'alias' => 'expired-files',
            'config_key' => 'expired_files',
            'options' => [
                'enabled' => (bool) env('DATA_CLEANUP_EXPIRED_FILES_ENABLED', false),

                /**
                 * Disk to clean (relative to storage_path())
                 * default is filesystems.default
                 */
                'disk' => env('DATA_CLEANUP_STORAGE_DISK', 'local'),

                /**
                 * Folders to clean (relative to storage_path())
                 * Can be a comma-separated string or array
                 */
                'folders' => env('DATA_CLEANUP_STORAGE_FOLDERS', 'temp,exports,tmp'),

                /**
                 * Number of days before a file is considered expired
                 */
                'expired_in_days' => (int) env('DATA_CLEANUP_STORAGE_EXPIRED_DAYS', 30),
            ],
        ],
        // Custom cleaner classes can be added here
    ],
];
