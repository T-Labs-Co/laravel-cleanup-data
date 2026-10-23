<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\AnonymizeAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\DeleteAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\NullifyAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\TransformAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategies\FixedStrategy;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel;

beforeEach(function (): void {
    Schema::create('anonymize_test_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamp('anonymized_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

test('DeleteAction force=false calls delete() and force=true calls forceDelete()', function (): void {
    $model1 = TestModel::create(['name' => 'Soft delete']);
    $actionSoft = new DeleteAction(force: false);
    $resultSoft = $actionSoft->execute($model1);

    expect($resultSoft)->toBeTrue()
        ->and(TestModel::find($model1->id))->toBeNull()
        ->and(TestModel::withTrashed()->find($model1->id))->not->toBeNull();

    $model2 = TestModel::create(['name' => 'Force delete']);
    $actionForce = new DeleteAction(force: true);
    $resultForce = $actionForce->execute($model2);

    expect($resultForce)->toBeTrue()
        ->and(TestModel::withTrashed()->find($model2->id))->toBeNull();
});

test('AnonymizeAction skips row when anonymized_at is set, sets marker on first run, and respects quietly flag', function (): void {
    $rowClass = new class extends TestModel
    {
        protected $table = 'anonymize_test_models';
    };

    // 1. First run on non-anonymized row sets marker and anonymizes fields
    $row = $rowClass::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $action = new AnonymizeAction(
        fields: ['name' => new FixedStrategy('ANONYMOUS')],
        markerColumn: 'anonymized_at',
        quietly: true
    );

    $executed = $action->execute($row);
    expect($executed)->toBeTrue();

    $freshRow = $rowClass::find($row->id);
    expect($freshRow->name)->toBe('ANONYMOUS')
        ->and($freshRow->anonymized_at)->not->toBeNull();

    // 2. Skips row when anonymized_at is set
    $secondExecution = $action->execute($freshRow);
    expect($secondExecution)->toBeFalse();

    // Preview should return skip marker if already marked
    expect($action->preview($freshRow))->toBe(['*' => 'skip (already anonymized)']);

    // 3. quietly=false calls save() so Eloquent model events fire
    $row2 = $rowClass::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $actionNonQuiet = new AnonymizeAction(
        fields: ['name' => new FixedStrategy('ANONYMOUS')],
        markerColumn: 'anonymized_at',
        quietly: false
    );

    $savingFired = false;
    $rowClass::saving(function () use (&$savingFired): void {
        $savingFired = true;
    });

    $actionNonQuiet->execute($row2);
    expect($savingFired)->toBeTrue();
});

test('NullifyAction fills all specified columns with null and respects quietly flag', function (): void {
    $rowClass = new class extends TestModel
    {
        protected $table = 'anonymize_test_models';
    };

    $row = $rowClass::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $action = new NullifyAction(columns: ['name', 'email'], quietly: true);

    $executed = $action->execute($row);
    expect($executed)->toBeTrue();

    $freshRow = $rowClass::find($row->id);
    expect($freshRow->name)->toBeNull()
        ->and($freshRow->email)->toBeNull();

    // preview returns 'null'
    expect($action->preview($row))->toBe(['name' => 'null', 'email' => 'null']);

    // quietly = false calls save()
    $row2 = $rowClass::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $actionNonQuiet = new NullifyAction(columns: ['name'], quietly: false);

    $savingFired = false;
    $rowClass::saving(function () use (&$savingFired): void {
        $savingFired = true;
    });

    $actionNonQuiet->execute($row2);
    expect($savingFired)->toBeTrue();
});

test('TransformAction is idempotent (no write when value unchanged) and respects quietly flag', function (): void {
    $rowClass = new class extends TestModel
    {
        protected $table = 'anonymize_test_models';
    };

    $row = $rowClass::create(['name' => '  untrimmed name  ', 'email' => 'test@example.com']);

    // Transform: trim name
    $action = new TransformAction(
        transforms: [
            'name' => fn ($val) => is_string($val) ? trim($val) : $val,
        ],
        quietly: true
    );

    // First execution: changes value
    $executed = $action->execute($row);
    expect($executed)->toBeTrue();

    $freshRow = $rowClass::find($row->id);
    expect($freshRow->name)->toBe('untrimmed name');

    // Second execution on already trimmed row: idempotent, returns false, nothing written
    $secondExecution = $action->execute($freshRow);
    expect($secondExecution)->toBeFalse();
    expect($action->preview($freshRow))->toBe([]);

    // quietly=false calls save()
    $row2 = $rowClass::create(['name' => '  another name  ', 'email' => 'test2@example.com']);
    $actionNonQuiet = new TransformAction(
        transforms: [
            'name' => fn ($val) => is_string($val) ? trim($val) : $val,
        ],
        quietly: false
    );

    $savingFired = false;
    $rowClass::saving(function () use (&$savingFired): void {
        $savingFired = true;
    });

    $actionNonQuiet->execute($row2);
    expect($savingFired)->toBeTrue();
});
