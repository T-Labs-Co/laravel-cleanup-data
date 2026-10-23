<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use TLabsCo\LaravelCleanupData\Core\Cleaners\RuleBasedCleaner;
use TLabsCo\LaravelCleanupData\Core\Retention\Action;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Exceptions\SafetyLimitExceeded;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel;

test('RuleBasedCleaner safety limit aborts before any changes when exceeded', function (): void {
    TestModel::create(['name' => 'Item 1']);
    TestModel::create(['name' => 'Item 2']);
    TestModel::create(['name' => 'Item 3']);

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Test Cleaner';
        }

        public function getDescription(): string
        {
            return 'Test Description';
        }

        protected function model(): string
        {
            return TestModel::class;
        }

        protected function rules(): array
        {
            return []; // matches all 3
        }

        protected function action(): CleanupAction
        {
            return Action::delete();
        }

        protected function maxAffected(): int
        {
            return 2; // cap is 2, matched is 3
        }
    };

    expect(fn () => $cleaner->cleanup(dryRun: false))
        ->toThrow(SafetyLimitExceeded::class);

    // Verify nothing was touched/deleted
    expect(TestModel::count())->toBe(3);
});

test('RuleBasedCleaner dry-run returns sample without touching data', function (): void {
    TestModel::create(['name' => 'Item 1']);
    TestModel::create(['name' => 'Item 2']);

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Test Cleaner';
        }

        public function getDescription(): string
        {
            return 'Test Description';
        }

        protected function model(): string
        {
            return TestModel::class;
        }

        protected function rules(): array
        {
            return [];
        }

        protected function action(): CleanupAction
        {
            return Action::delete();
        }
    };

    $result = $cleaner->cleanup(dryRun: true);

    expect($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(0)
        ->and($result->message)->toContain('Dry run: 2 rows would be affected')
        ->and(TestModel::count())->toBe(2);
});

test('RuleBasedCleaner chunk iteration processes in configured chunks', function (): void {
    for ($i = 1; $i <= 5; $i++) {
        TestModel::create(['name' => "Item {$i}"]);
    }

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Test Cleaner';
        }

        public function getDescription(): string
        {
            return 'Test Description';
        }

        protected function model(): string
        {
            return TestModel::class;
        }

        protected function rules(): array
        {
            return [];
        }

        protected function action(): CleanupAction
        {
            return Action::delete();
        }

        protected function chunkSize(): int
        {
            return 2; // chunk size of 2
        }
    };

    $result = $cleaner->cleanup(dryRun: false);

    expect($result->itemsProcessed)->toBe(5)
        ->and($result->itemsDeleted)->toBe(5)
        ->and(TestModel::count())->toBe(0);
});

test('RuleBasedCleaner scopeQuery is a no-op by default', function (): void {
    TestModel::create(['name' => 'Active']);
    $trashed = TestModel::create(['name' => 'Deleted']);
    $trashed->delete();

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Test Cleaner';
        }

        public function getDescription(): string
        {
            return 'Test Description';
        }

        protected function model(): string
        {
            return TestModel::class;
        }

        protected function rules(): array
        {
            return [];
        }

        protected function action(): CleanupAction
        {
            return Action::delete(force: true);
        }
    };

    // By default, standard Eloquent query excludes soft-deleted rows
    $result = $cleaner->cleanup(dryRun: false);

    expect($result->itemsProcessed)->toBe(1)
        ->and($result->itemsDeleted)->toBe(1)
        ->and(TestModel::count())->toBe(0)
        ->and(TestModel::withTrashed()->count())->toBe(1); // Trashed row was not touched
});

test('RuleBasedCleaner overriding scopeQuery with onlyTrashed includes only trashed rows', function (): void {
    TestModel::create(['name' => 'Active']);
    $trashed = TestModel::create(['name' => 'Deleted']);
    $trashed->delete();

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Test Cleaner';
        }

        public function getDescription(): string
        {
            return 'Test Description';
        }

        protected function model(): string
        {
            return TestModel::class;
        }

        protected function rules(): array
        {
            return [];
        }

        protected function action(): CleanupAction
        {
            return Action::delete(force: true);
        }

        protected function scopeQuery(Builder $query): void
        {
            $query->onlyTrashed();
        }
    };

    $result = $cleaner->cleanup(dryRun: false);

    expect($result->itemsProcessed)->toBe(1)
        ->and($result->itemsDeleted)->toBe(1)
        ->and(TestModel::count())->toBe(1) // Active row remains
        ->and(TestModel::withTrashed()->count())->toBe(1)
        ->and(TestModel::onlyTrashed()->count())->toBe(0); // Trashed row force deleted
});
