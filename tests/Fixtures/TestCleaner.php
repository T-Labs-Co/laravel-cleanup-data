<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Tests\Fixtures;

use TLabsCo\LaravelCleanupData\Core\Cleaners\BaseCleaner;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

class TestCleaner extends BaseCleaner
{
    public bool $ran = false;

    public bool $wasDryRun = false;

    protected array $tags = ['test', 'fixture'];

    public function getName(): string
    {
        return 'Test Cleaner';
    }

    public function getDescription(): string
    {
        return 'Cleaner for testing purposes';
    }

    protected function getConfigPrefix(): string
    {
        return 'test_cleaner';
    }

    public function cleanup(bool $dryRun = false): CleanupResult
    {
        $this->ran = true;
        $this->wasDryRun = $dryRun;

        return CleanupResult::success(
            cleaner: $this->getName(),
            processed: 10,
            deleted: $dryRun ? 0 : 10,
            message: 'Cleaned test items',
        );
    }
}
