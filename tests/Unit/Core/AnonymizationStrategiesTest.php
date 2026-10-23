<?php

declare(strict_types=1);

use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\HashedEmailStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\MaskStrategy;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel;

test('HashedEmailStrategy generates unique output per model PK and contains @ domain', function (): void {
    $model1 = TestModel::create(['name' => 'User 1']);
    $model2 = TestModel::create(['name' => 'User 2']);

    $strategy = new HashedEmailStrategy(domain: 'example.test');

    $email1 = $strategy->apply('original1@test.com', $model1, 'email');
    $email2 = $strategy->apply('original2@test.com', $model2, 'email');

    expect($email1)->toContain('@example.test')
        ->and($email2)->toContain('@example.test')
        ->and($email1)->not->toBe($email2)
        ->and($strategy->label())->toBe('hashed_email');

    // Default domain fallback
    $defaultStrategy = new HashedEmailStrategy;
    $emailDefault = $defaultStrategy->apply('test@test.com', $model1, 'email');
    expect($emailDefault)->toContain('@anon.invalid');
});

test('MaskStrategy handles keepLast boundary and handles empty/null gracefully', function (): void {
    $model = new TestModel;

    // keepLast = 0: entire string masked
    $mask0 = new MaskStrategy(keepLast: 0, char: '*');
    expect($mask0->apply('0912345678', $model, 'phone'))->toBe('**********');

    // keepLast = 3: keep last 3 characters
    $mask3 = new MaskStrategy(keepLast: 3, char: '*');
    expect($mask3->apply('0912345678', $model, 'phone'))->toBe('*******678');

    // keepLast greater than or equal to string length: nothing masked
    $maskLarge = new MaskStrategy(keepLast: 20, char: '*');
    expect($maskLarge->apply('0912345678', $model, 'phone'))->toBe('0912345678');

    // handles empty and null gracefully
    expect($mask3->apply(null, $model, 'phone'))->toBeNull()
        ->and($mask3->apply('', $model, 'phone'))->toBe('');

    expect($mask3->label())->toBe('mask');
});
