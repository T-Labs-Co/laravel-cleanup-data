<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerTagsParser;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestCleaner;

it('registers and retrieves cleaners by class and alias', function () {
    $registry = new CleanupRegistry(new CleanerTagsParser);
    $registry->register(TestCleaner::class, 'test-alias');

    expect($registry->has(TestCleaner::class))->toBeTrue()
        ->and($registry->has('test-alias'))->toBeTrue()
        ->and($registry->getCleaner('test-alias'))->toBeInstanceOf(TestCleaner::class)
        ->and($registry->getCleaner(TestCleaner::class))->toBeInstanceOf(TestCleaner::class);
});

it('throws exception if class does not implement CleanerContract', function () {
    $registry = new CleanupRegistry(new CleanerTagsParser);
    $registry->register(stdClass::class);
})->throws(InvalidArgumentException::class);

it('filters cleaners by tag', function () {
    $registry = new CleanupRegistry(new CleanerTagsParser);
    $registry->register(TestCleaner::class, 'test-cleaner');

    $matching = $registry->byTag('test');
    $notMatching = $registry->byTag('non-existent-tag');

    expect($matching)->toHaveCount(1)
        ->and($notMatching)->toHaveCount(0);
});

it('returns all registered cleaner instances', function () {
    $registry = new CleanupRegistry(new CleanerTagsParser);
    $registry->register(TestCleaner::class, 'test-cleaner');

    $instances = $registry->instances();

    expect($instances)->toHaveCount(1)
        ->and($instances->first())->toBeInstanceOf(TestCleaner::class);
});
