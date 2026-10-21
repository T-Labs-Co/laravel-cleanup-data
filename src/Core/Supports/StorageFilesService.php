<?php

namespace TLabsCo\LaravelCleanupData\Core\Supports;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageFilesService
{
    private readonly string $defaultDisk;

    public function __construct()
    {
        $this->defaultDisk = config('filesystems.default');
    }

    private function getDisk(?string $disk = null): string
    {
        return ! empty($disk) ? $disk : $this->defaultDisk;
    }

    /**
     * @return Filesystem|FilesystemAdapter
     */
    private function getStorageAdapter(?string $disk = null)
    {
        return Storage::disk($this->getDisk($disk));
    }

    /**
     * @param  string|array<string>  $folder
     */
    public function cleanExpiredFiles(string|array $folder, int $expired = 7, ?string $disk = null): void
    {
        $folders = is_array($folder) ? $folder : [$folder];

        $storage = $this->getStorageAdapter($disk);

        $expiredTimestamp = now()->subDays($expired)->getTimestamp();
        foreach ($folders as $dir) {

            if (! $storage->exists($dir)) {
                continue;
            }

            Log::info('*Start clean folder: '.$storage->path($dir));

            $clean = collect();

            /* @phpstan-ignore-next-line */
            collect($storage->getDriver()->listContents($dir, true))
                ->each(function ($file) use (&$clean, $storage, $expiredTimestamp): void {
                    if ($file['type'] === 'file' && $file['lastModified'] < $expiredTimestamp) {
                        $clean->push($file['path']);
                        $storage->delete($file['path']);
                    }
                });

            Log::info('*Clean expired files:');
            Log::info($clean);
        }
    }

    public function lastModifiedFileInFolder(string $folder, string $prefix = '', ?string $disk = null): ?string
    {
        $files = collect();

        $storage = $this->getStorageAdapter($disk);

        /* @phpstan-ignore-next-line */
        collect($storage->getDriver()->listContents($folder, true))
            ->each(function ($file) use (&$files, $prefix): void {
                if ($file['type'] == 'file') {
                    if ($prefix) {
                        if (Str::startsWith(basename((string) $file['path']), $prefix)) {
                            $files->push($file);
                        }
                    } else {
                        $files->push($file);
                    }
                }
            });

        /** @var array{path?: string, lastModified?: int}|null $lastFile */
        $lastFile = $files->sortByDesc('lastModified')->first();

        return $lastFile ? Arr::get($lastFile, 'path') : null;
    }

    /**
     * Count expired files in folders
     *
     * @param  array<string>  $folders
     */
    public function countExpiredFiles(array $folders, int $expiredInDays, ?string $disk = null): int
    {
        $count = 0;
        $storage = $this->getStorageAdapter($disk);
        $expiredTimestamp = now()->subDays($expiredInDays)->getTimestamp();

        foreach ($folders as $dir) {
            if (! $storage->exists($dir)) {
                continue;
            }

            /* @phpstan-ignore-next-line */
            collect($storage->getDriver()->listContents($dir, true))
                ->each(function ($file) use (&$count, $expiredTimestamp): void {
                    if ($file['type'] === 'file' && $file['lastModified'] < $expiredTimestamp) {
                        $count++;
                    }
                });
        }

        return $count;
    }
}
