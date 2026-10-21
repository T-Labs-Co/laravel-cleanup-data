<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

it('creates success result correctly', function () {
    $result = CleanupResult::success(
        cleaner: 'TestCleaner',
        processed: 100,
        deleted: 50,
        message: 'Cleanup completed successfully',
        details: ['foo' => 'bar']
    );

    expect($result->cleaner)->toBe('TestCleaner')
        ->and($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(100)
        ->and($result->itemsDeleted)->toBe(50)
        ->and($result->message)->toBe('Cleanup completed successfully')
        ->and($result->details)->toBe(['foo' => 'bar'])
        ->and($result->exception)->toBeNull();
});

it('creates failure result correctly', function () {
    $exception = new RuntimeException('Database error occurred');
    $result = CleanupResult::failure(
        cleaner: 'TestCleaner',
        exception: $exception,
        message: 'Custom failure message'
    );

    expect($result->cleaner)->toBe('TestCleaner')
        ->and($result->success)->toBeFalse()
        ->and($result->itemsProcessed)->toBe(0)
        ->and($result->itemsDeleted)->toBe(0)
        ->and($result->message)->toBe('Custom failure message')
        ->and($result->exception)->toBe($exception);
});

it('creates skipped result correctly', function () {
    $result = CleanupResult::skipped(
        cleaner: 'TestCleaner',
        reason: 'Feature disabled in configuration'
    );

    expect($result->cleaner)->toBe('TestCleaner')
        ->and($result->success)->toBeTrue()
        ->and($result->itemsProcessed)->toBe(0)
        ->and($result->itemsDeleted)->toBe(0)
        ->and($result->message)->toBe('Skipped: Feature disabled in configuration');
});
