<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\ExceptRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\OlderThanRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\WhereRule;
use TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-23 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('OlderThanRule filters by cutoff boundary across days, months, and years combinations', function (): void {
    // 10 days old
    TestModel::create(['name' => '10-days', 'created_at' => now()->subDays(10)]);
    // 35 days old
    TestModel::create(['name' => '35-days', 'created_at' => now()->subDays(35)]);
    // 2 months old (~60 days)
    TestModel::create(['name' => '2-months', 'created_at' => now()->subMonths(2)]);
    // 1 year and 1 month old
    TestModel::create(['name' => '1-year-1-month', 'created_at' => now()->subYears(1)->subMonths(1)]);
    // 2 years old
    TestModel::create(['name' => '2-years', 'created_at' => now()->subYears(2)]);

    // Test days cutoff: older than 30 days
    $qDays = TestModel::query();
    (new OlderThanRule(column: 'created_at', days: 30))->apply($qDays);
    $daysResults = $qDays->pluck('name')->all();
    expect($daysResults)->not->toContain('10-days')
        ->and($daysResults)->toContain('35-days', '2-months', '1-year-1-month', '2-years');

    // Test months cutoff: older than 1 month (30/31 days)
    $qMonths = TestModel::query();
    (new OlderThanRule(column: 'created_at', months: 1))->apply($qMonths);
    $monthsResults = $qMonths->pluck('name')->all();
    expect($monthsResults)->not->toContain('10-days')
        ->and($monthsResults)->toContain('35-days', '2-months', '1-year-1-month', '2-years');

    // Test years cutoff: older than 1 year
    $qYears = TestModel::query();
    (new OlderThanRule(column: 'created_at', years: 1))->apply($qYears);
    $yearsResults = $qYears->pluck('name')->all();
    expect($yearsResults)->not->toContain('10-days', '35-days', '2-months')
        ->and($yearsResults)->toContain('1-year-1-month', '2-years');

    // Test combination: 1 year, 1 month, 5 days
    $qCombo = TestModel::query();
    (new OlderThanRule(column: 'created_at', days: 5, months: 1, years: 1))->apply($qCombo);
    $comboResults = $qCombo->pluck('name')->all();
    expect($comboResults)->not->toContain('10-days', '35-days', '2-months', '1-year-1-month')
        ->and($comboResults)->toContain('2-years');
});

test('WhereRule closure receives correct Builder instance', function (): void {
    TestModel::create(['name' => 'Keep']);
    TestModel::create(['name' => 'DeleteMe']);

    $receivedBuilder = null;

    $rule = new WhereRule(function (Builder $query) use (&$receivedBuilder): void {
        $receivedBuilder = $query;
        $query->where('name', 'DeleteMe');
    });

    $query = TestModel::query();
    $rule->apply($query);

    expect($receivedBuilder)->toBe($query)
        ->and($query->pluck('name')->all())->toBe(['DeleteMe']);
});

test('ExceptRule inverts the inner rule via whereNot', function (): void {
    TestModel::create(['name' => 'Protected']);
    TestModel::create(['name' => 'Eligible 1']);
    TestModel::create(['name' => 'Eligible 2']);

    $inner = new WhereRule(fn (Builder $q) => $q->where('name', 'Protected'));
    $exceptRule = new ExceptRule($inner);

    $query = TestModel::query();
    $exceptRule->apply($query);

    $results = $query->pluck('name')->all();
    expect($results)->not->toContain('Protected')
        ->and($results)->toContain('Eligible 1', 'Eligible 2');
});
