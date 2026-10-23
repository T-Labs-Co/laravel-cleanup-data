<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use TLabsCo\LaravelCleanupData\Core\Cleaners\RuleBasedCleaner;
use TLabsCo\LaravelCleanupData\Core\Retention\Action;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\AnonymizeAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Exceptions\SafetyLimitExceeded;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategy;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;
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

test('RuleBasedCleaner anonymize action excludes already anonymized from query in force mode', function (): void {
    // Create 3 models: 2 not anonymized, 1 already anonymized
    TestModel::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    TestModel::create(['name' => 'User 2', 'email' => 'user2@test.com']);
    $anonymized = TestModel::create(['name' => 'User 3', 'email' => 'user3@test.com', 'anonymized_at' => now()]);

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Anonymize Cleaner';
        }

        public function getDescription(): string
        {
            return 'Anonymize test';
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
            return Action::anonymize([
                'email' => Strategy::fixed('anon@test.com'),
            ]);
        }
    };

    $result = $cleaner->cleanup(dryRun: false);

    // Only 2 non-anonymized rows should be matched and processed
    expect($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(2);

    // All 3 records now have anonymized_at set (2 just anonymized + 1 pre-anonymized)
    expect(TestModel::whereNull('anonymized_at')->count())->toBe(0)
        ->and(TestModel::where('id', $anonymized->id)->first()->anonymized_at)->not->toBeNull();
});

test('RuleBasedCleaner anonymize action excludes already anonymized from query in dry run mode', function (): void {
    TestModel::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    TestModel::create(['name' => 'User 2', 'email' => 'user2@test.com']);
    TestModel::create(['name' => 'User 3', 'email' => 'user3@test.com', 'anonymized_at' => now()]);

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Anonymize Cleaner';
        }

        public function getDescription(): string
        {
            return 'Anonymize test';
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
            return Action::anonymize([
                'email' => Strategy::fixed('anon@test.com'),
            ]);
        }
    };

    $result = $cleaner->cleanup(dryRun: true);

    // Only 2 non-anonymized rows should be matched
    expect($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(0)
        ->and($result->message)->toContain('Dry run: 2 rows would be affected');

    // No data should be modified
    expect(TestModel::whereNull('anonymized_at')->count())->toBe(2);
});

test('RuleBasedCleaner anonymize action includes already anonymized when config disabled', function (): void {
    TestModel::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    TestModel::create(['name' => 'User 2', 'email' => 'user2@test.com', 'anonymized_at' => now()]);

    // Disable skip_anonymized_in_query via config
    config(['laravel-cleanup-data.retention.skip_anonymized_in_query' => false]);

    $cleaner = new class extends RuleBasedCleaner
    {
        public function getName(): string
        {
            return 'Anonymize Cleaner';
        }

        public function getDescription(): string
        {
            return 'Anonymize test';
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
            return Action::anonymize([
                'email' => Strategy::fixed('anon@test.com'),
            ]);
        }
    };

    $result = $cleaner->cleanup(dryRun: true);

    // Both rows should be matched (already anonymized is NOT excluded from query)
    expect($result->itemsProcessed)->toBe(2);

    // Reset config
    config(['laravel-cleanup-data.retention.skip_anonymized_in_query' => true]);
});

test('ModelPruner anonymize excludes already anonymized from query in force mode', function (): void {
    TestModel::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    TestModel::create(['name' => 'User 2', 'email' => 'user2@test.com']);
    TestModel::create(['name' => 'User 3', 'email' => 'user3@test.com', 'anonymized_at' => now()]);

    $result = ModelPruner::for(TestModel::class)
        ->anonymize([
            'email' => Strategy::fixed('anon@test.com'),
        ])
        ->clean();

    expect($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(2);
});

test('ModelPruner anonymize excludes already anonymized from query in dry run mode', function (): void {
    TestModel::create(['name' => 'User 1', 'email' => 'user1@test.com']);
    TestModel::create(['name' => 'User 2', 'email' => 'user2@test.com']);
    TestModel::create(['name' => 'User 3', 'email' => 'user3@test.com', 'anonymized_at' => now()]);

    $result = ModelPruner::for(TestModel::class)
        ->anonymize([
            'email' => Strategy::fixed('anon@test.com'),
        ])
        ->dryRun();

    expect($result->itemsProcessed)->toBe(2)
        ->and($result->itemsDeleted)->toBe(0);
});

test('AnonymizeAction applyToQuery adds whereNull on marker column', function (): void {
    $action = Action::anonymize([
        'email' => Strategy::fixed('anon@test.com'),
    ]);

    $query = TestModel::query();
    $action->applyToQuery($query);

    // Should have whereNull('anonymized_at') applied
    $sql = $query->toSql();
    expect($sql)->toContain('anonymized_at');
});

test('AnonymizeAction applyToQuery is skipped when config disabled', function (): void {
    config(['laravel-cleanup-data.retention.skip_anonymized_in_query' => false]);

    $action = new AnonymizeAction([
        'email' => Strategy::fixed('anon@test.com'),
    ]);

    $query = TestModel::query();
    $action->applyToQuery($query);

    $sql = $query->toSql();
    expect($sql)->not->toContain('anonymized_at');

    config(['laravel-cleanup-data.retention.skip_anonymized_in_query' => true]);
});

test('AnonymizeAction constructor skipAnonymizedInQuery param overrides config', function (): void {
    // Config is true by default, but we pass false explicitly
    $action = new AnonymizeAction([
        'email' => Strategy::fixed('anon@test.com'),
    ], skipAnonymizedInQuery: false);

    $query = TestModel::query();
    $action->applyToQuery($query);

    $sql = $query->toSql();
    expect($sql)->not->toContain('anonymized_at');
});
