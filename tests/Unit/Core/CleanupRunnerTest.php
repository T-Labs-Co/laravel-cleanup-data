<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Core\CleanupRunner;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerResolver;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerTagsParser;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestCleaner;

it('runs cleaners and propagates dryRun flag', function () {
    $cleaner = new TestCleaner;
    $registry = new CleanupRegistry(new CleanerTagsParser);
    $resolver = new CleanerResolver($registry);
    $runner = new CleanupRunner($registry, $resolver);

    $results = $runner->run([$cleaner], dryRun: true);

    expect($results)->toHaveCount(1)
        ->and($cleaner->ran)->toBeTrue()
        ->and($cleaner->wasDryRun)->toBeTrue()
        ->and($results[0]->success)->toBeTrue()
        ->and($results[0]->itemsProcessed)->toBe(10)
        ->and($results[0]->itemsDeleted)->toBe(0);
});

it('detects failures in batch results', function () {
    $registry = new CleanupRegistry(new CleanerTagsParser);
    $resolver = new CleanerResolver($registry);
    $runner = new CleanupRunner($registry, $resolver);

    $successResult = CleanupResult::success('SuccessCleaner', 10, 10);
    $failureResult = CleanupResult::failure('FailureCleaner', new Exception('Something went wrong'));

    expect($runner->hasFailures([$successResult]))->toBeFalse()
        ->and($runner->hasFailures([$successResult, $failureResult]))->toBeTrue();
});
