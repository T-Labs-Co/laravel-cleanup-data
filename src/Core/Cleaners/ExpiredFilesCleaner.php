<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Cleaners;

use TLabsCo\LaravelCleanupData\Core\Supports\CleanerTagsParser;
use TLabsCo\LaravelCleanupData\Core\Supports\StorageFilesService;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

/**
 * Cleaner for expired storage files
 *
 * Cleans old files from configured storage folders
 * Migrated from supports/Commands/CleanExpiredFileCommand
 */
class ExpiredFilesCleaner extends BaseCleaner
{
    protected array $tags = ['storage', 'files', 'daily'];

    public function __construct(
        protected StorageFilesService $filesService,
        protected CleanerTagsParser $folderParser,
    ) {}

    /**
     * Get the cleaner name
     */
    public function getName(): string
    {
        return 'Expired Storage Files';
    }

    /**
     * Get the cleaner description
     */
    public function getDescription(): string
    {
        return 'Clean expired files from configured storage folders';
    }

    /**
     * Get the config prefix
     */
    protected function getConfigPrefix(): string
    {
        return 'expired_files';
    }

    /**
     * Execute the cleanup
     *
     * @param  bool  $dryRun  If true, only count files without deleting
     */
    public function cleanup(bool $dryRun = false): CleanupResult
    {
        $folders = $this->config('folders', []);
        $expiredIn = (int) $this->config('expired_in_days', 30);
        $disk = $this->config('disk', config('filesystems.default'));
        $diskName = ! empty($disk) ? (string) $disk : (string) config('filesystems.default');

        if (empty($folders) || empty($diskName)) {
            return CleanupResult::skipped(
                $this->getName(),
                'No folders or disk configured in laravel-cleanup-data.expired_files.folders'
            );
        }

        if (! is_array($folders)) {
            $folders = $this->folderParser->execute($folders);
        }

        // Count files before deletion
        $totalFiles = $this->filesService->countExpiredFiles($folders, $expiredIn, $diskName);

        // Only delete if not in dry-run mode
        if (! $dryRun && $totalFiles > 0) {
            $this->filesService->cleanExpiredFiles($folders, $expiredIn, $diskName);
        }

        $message = sprintf(
            '%s %d files older than %d days from %d folder(s)',
            $dryRun ? 'Would delete' : 'Deleted',
            $totalFiles,
            $expiredIn,
            count($folders)
        );

        return CleanupResult::success(
            cleaner: $this->getName(),
            processed: $totalFiles,
            deleted: $dryRun ? 0 : $totalFiles,
            message: $message,
            details: [
                'disk' => $diskName,
                'folders' => $folders,
                'expired_in_days' => $expiredIn,
            ]
        );
    }
}
