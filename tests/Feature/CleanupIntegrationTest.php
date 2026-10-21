<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Facades\Cleaner;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel;

it('runs model prune via facade', function () {
    TestModel::create(['name' => 'Old record', 'created_at' => now()->subDays(45)]);
    TestModel::create(['name' => 'Recent record', 'created_at' => now()->subDays(5)]);

    $result = Cleaner::model(TestModel::class)
        ->days(30)
        ->clean();

    expect($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(1)
        ->and($result->itemsDeleted)->toBe(1)
        ->and(TestModel::count())->toBe(1);
});

it('executes generic model cleaner configured in config', function () {
    config()->set('laravel-cleanup-data.cleaners.prune_models', [
        [
            'name' => 'test-models',
            'model' => TestModel::class,
            'column' => 'created_at',
            'days' => 20,
            'force' => true,
            'enabled' => true,
        ],
    ]);

    TestModel::create(['name' => 'Old record', 'created_at' => now()->subDays(30)]);
    TestModel::create(['name' => 'Recent record', 'created_at' => now()->subDays(5)]);

    $this->artisan('cleanup:run', ['--cleaner' => 'TLabsCo\LaravelCleanupData\Core\Cleaners\GenericModelCleaner', '--force' => true])
        ->assertSuccessful();

    expect(TestModel::withTrashed()->count())->toBe(1);
});
