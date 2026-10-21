<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel;

it('builds configuration correctly with fluent methods', function () {
    $pruner = ModelPruner::for(TestModel::class)
        ->column('updated_at')
        ->days(60)
        ->force(true)
        ->name('CustomTestModel');

    $config = $pruner->toArray();

    expect($config['name'])->toBe('CustomTestModel')
        ->and($config['model'])->toBe(TestModel::class)
        ->and($config['column'])->toBe('updated_at')
        ->and($config['days'])->toBe(60)
        ->and($config['force'])->toBeTrue()
        ->and($config['enabled'])->toBeTrue();
});

it('prunes models older than given days', function () {
    TestModel::create(['name' => 'Old 1', 'created_at' => now()->subDays(40)]);
    TestModel::create(['name' => 'Old 2', 'created_at' => now()->subDays(35)]);
    TestModel::create(['name' => 'Recent', 'created_at' => now()->subDays(10)]);

    $result = ModelPruner::for(TestModel::class)
        ->days(30)
        ->clean();

    expect($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(2)
        ->and(TestModel::count())->toBe(1)
        ->and(TestModel::withTrashed()->count())->toBe(3);
});

it('force deletes models when force is enabled', function () {
    TestModel::create(['name' => 'Old 1', 'created_at' => now()->subDays(40)]);
    TestModel::create(['name' => 'Recent', 'created_at' => now()->subDays(5)]);

    $result = ModelPruner::for(TestModel::class)
        ->days(30)
        ->force(true)
        ->clean();

    expect($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(1)
        ->and($result->itemsDeleted)->toBe(1)
        ->and(TestModel::withTrashed()->count())->toBe(1);
});

it('dryRun does not delete records', function () {
    TestModel::create(['name' => 'Old 1', 'created_at' => now()->subDays(40)]);
    TestModel::create(['name' => 'Old 2', 'created_at' => now()->subDays(35)]);

    $result = ModelPruner::for(TestModel::class)
        ->days(30)
        ->dryRun();

    expect($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(0)
        ->and(TestModel::count())->toBe(2);
});
