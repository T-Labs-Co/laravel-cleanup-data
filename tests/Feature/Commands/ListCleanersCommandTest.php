<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestCleaner;

beforeEach(function () {
    /** @var CleanupRegistry $registry */
    $registry = app(CleanupRegistry::class);
    $registry->register(TestCleaner::class, 'test-cleaner');
});

it('lists registered cleaners', function () {
    $this->artisan('cleanup:list')
        ->expectsOutputToContain('Test Cleaner')
        ->assertSuccessful();
});

it('filters listed cleaners by tag', function () {
    $this->artisan('cleanup:list', ['--tag' => 'fixture'])
        ->expectsOutputToContain('Test Cleaner')
        ->assertSuccessful();

    $this->artisan('cleanup:list', ['--tag' => 'nonexistent'])
        ->expectsOutputToContain('No cleaners found.')
        ->assertSuccessful();
});
