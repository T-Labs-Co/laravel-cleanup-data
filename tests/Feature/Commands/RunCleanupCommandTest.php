<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestCleaner;

beforeEach(function () {
    /** @var CleanupRegistry $registry */
    $registry = app(CleanupRegistry::class);
    $registry->register(TestCleaner::class, 'test-cleaner');
});

it('runs cleanup with force option successfully', function () {
    $this->artisan('cleanup:run', ['--force' => true])
        ->expectsOutputToContain('Starting cleanup process...')
        ->expectsOutputToContain('Test Cleaner')
        ->expectsOutputToContain('Deleted 10 items')
        ->assertSuccessful();
});

it('runs cleanup in dry-run mode without deleting data', function () {
    $this->artisan('cleanup:run', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('Would process 10 items')
        ->assertSuccessful();
});

it('filters cleaners by tag', function () {
    $this->artisan('cleanup:run', ['--tag' => 'fixture', '--force' => true])
        ->expectsOutputToContain('Test Cleaner')
        ->assertSuccessful();

    $this->artisan('cleanup:run', ['--tag' => 'nonexistent', '--force' => true])
        ->expectsOutputToContain('No cleaners found to run.')
        ->assertFailed();
});

it('filters by cleaner class option', function () {
    $this->artisan('cleanup:run', ['--cleaner' => TestCleaner::class, '--force' => true])
        ->expectsOutputToContain('Test Cleaner')
        ->assertSuccessful();

    $this->artisan('cleanup:run', ['--cleaner' => 'NonExistentCleanerClass', '--force' => true])
        ->expectsOutputToContain('Cleaner not found: NonExistentCleanerClass')
        ->assertFailed();
});
