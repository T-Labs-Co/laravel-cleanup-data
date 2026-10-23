# Laravel Cleanup Data

[![Tests](https://github.com/t-labs-co/laravel-cleanup-data/actions/workflows/tests.yml/badge.svg)](https://github.com/t-labs-co/laravel-cleanup-data/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/t-labs-co/laravel-cleanup-data.svg)](https://packagist.org/packages/t-labs-co/laravel-cleanup-data)
[![License](https://img.shields.io/packagist/l/t-labs-co/laravel-cleanup-data.svg)](https://packagist.org/packages/t-labs-co/laravel-cleanup-data)
[![PHP](https://img.shields.io/packagist/php-v/t-labs-co/laravel-cleanup-data.svg)](https://packagist.org/packages/t-labs-co/laravel-cleanup-data)

Centralized data-retention and cleanup framework for Laravel.
Prune Eloquent models, clean storage files, run custom cleanup logic, and schedule operations — all from a single config.

## Features

- 🚀 **Centralized Cleanup Registry**: Register and execute cleanup actions with tag and alias support.
- 🧹 **Generic Model Pruner**: Easily prune records from Eloquent models based on date/time columns via config or fluent builder.
- 📁 **Expired Files Cleaner**: Clean up old files from storage disks and directories automatically.
- ⚡ **Fluent API & Facade**: Convenient `Cleaner::model(User::class)->days(30)->clean()` API for ad-hoc cleanup.
- 🔍 **Dry-Run Mode**: Safely preview items that would be deleted without touching real data.
- ⏰ **Built-in Scheduling**: Config-driven automated cron scheduling.
- 🪝 **Hook & Logging System**: Lifecycle hooks (`before`, `after`, logging) for comprehensive observability and error tracking.
- 📋 **Rule-Based Cleaners** *(v1.1.0)*: Compose `RetentionRule` objects with any `CleanupAction` — no boilerplate query code required.
- 🔒 **Anonymization** *(v1.1.0)*: GDPR-ready field-level anonymization with idempotency guard (`anonymized_at`) and built-in strategies (hashed email, mask, null, fixed, custom closure).
- 🛡️ **Safety Limit** *(v1.1.0)*: Hard cap per run — aborts before touching data when matched rows exceed `max_affected`.

## Requirements

- PHP 8.4+
- Laravel 12.0+ or 13.0+

## Installation

```bash
composer require t-labs-co/laravel-cleanup-data
```

Publish configuration file:
```bash
php artisan vendor:publish --tag=laravel-cleanup-data-config
```

## Quick Start (3 minutes)

### 1. Fluent Facade Pruning

```php
use TLabsCo\LaravelCleanupData\Facades\Cleaner;
use App\Models\ActivityLog;

// Delete soft-deletable or standard records older than 30 days
$result = Cleaner::model(ActivityLog::class)
    ->days(30)
    ->clean();

// Preview without deleting
$preview = Cleaner::model(ActivityLog::class)
    ->days(30)
    ->dryRun();
```

### 2. Rule-Based Pruning (v1.1.0)

Compose rules and actions fluently on `ModelPruner`:

```php
use TLabsCo\LaravelCleanupData\Core\Retention\Action;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategy;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;

// Delete inactive users older than 2 years, except those on legal hold
$result = ModelPruner::for(User::class)
    ->olderThan(years: 2)
    ->where('status', 'inactive')
    ->except(fn ($q) => $q->where('legal_hold', true))
    ->action(Action::delete())
    ->maxAffected(5_000)
    ->clean();

// Anonymize PII fields on users older than 1 year (idempotent)
$result = ModelPruner::for(User::class)
    ->olderThan(years: 1)
    ->anonymize([
        'email' => Strategy::hashedEmail(),
        'phone' => Strategy::mask(keepLast: 4),
        'name'  => Strategy::fixed('Anonymized User'),
    ])
    ->clean();
```

### 3. Config-Driven Model Pruning

In `config/laravel-cleanup-data.php`:

```php
'cleaners' => [
    'prune_models' => [
        [
            'name'    => 'activity-logs',
            'model'   => App\Models\ActivityLog::class,
            'column'  => 'created_at',
            'days'    => 30,
            'force'   => false,
            'enabled' => true,
        ],
    ],
],
```

### 4. Artisan Commands

```bash
# Run all enabled cleaners
php artisan cleanup:run

# Preview cleanup in dry-run mode
php artisan cleanup:run --dry-run

# Run specific tag or cleaner
php artisan cleanup:run --tag=storage --force
php artisan cleanup:run --cleaner=generic-models

# List all registered cleaners
php artisan cleanup:list
```

## Documentation

### Creating Custom Cleaners

Extend `TLabsCo\LaravelCleanupData\Core\Cleaners\BaseCleaner` and implement `cleanup()`:

```php
namespace App\Cleaners;

use TLabsCo\LaravelCleanupData\Core\Cleaners\BaseCleaner;
use TLabsCo\LaravelCleanupData\Core\ValueObject\CleanupResult;

class TempExportCleaner extends BaseCleaner
{
    protected array $tags = ['storage', 'exports', 'daily'];

    public function getName(): string
    {
        return 'Temp Export Cleaner';
    }

    public function getDescription(): string
    {
        return 'Prunes temporary export files older than 7 days';
    }

    protected function getConfigPrefix(): string
    {
        return 'temp_exports';
    }

    public function cleanup(bool $dryRun = false): CleanupResult
    {
        // Custom cleanup logic...
        return CleanupResult::success(
            cleaner: $this->getName(),
            processed: 15,
            deleted: $dryRun ? 0 : 15,
            message: 'Cleaned 15 temporary exports'
        );
    }
}
```

Register your custom cleaner in `config/laravel-cleanup-data.php`:

```php
'cleaners_classes' => [
    \App\Cleaners\TempExportCleaner::class => [
        'alias' => 'temp-exports',
        'config_key' => 'temp_exports',
    ],
],
```

### Creating Rule-Based Cleaners (v1.1.0)

Extend `RuleBasedCleaner` and declare `model()`, `rules()`, and `action()`. The engine handles chunking, dry-run, safety limits, and logging automatically.

```php
namespace App\Cleaners;

use TLabsCo\LaravelCleanupData\Core\Cleaners\RuleBasedCleaner;
use TLabsCo\LaravelCleanupData\Core\Retention\Action;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;
use TLabsCo\LaravelCleanupData\Core\Retention\Rules\OlderThanRule;
use App\Models\User;

class InactiveUserCleaner extends RuleBasedCleaner
{
    protected array $tags = ['users', 'gdpr', 'weekly'];

    public function getName(): string { return 'Inactive User Cleaner'; }
    public function getDescription(): string { return 'Deletes inactive users older than 2 years'; }

    protected function model(): string { return User::class; }

    protected function rules(): array
    {
        return [
            new OlderThanRule(column: 'last_login_at', years: 2),
        ];
    }

    protected function action(): CleanupAction
    {
        return Action::delete();
    }
}
```

### Anonymization (v1.1.0)

Use `RuleBasedCleaner` or `ModelPruner` with `AnonymizeAction` to pseudonymize PII fields. The action is idempotent — records with a non-null `anonymized_at` column are skipped.

```php
use TLabsCo\LaravelCleanupData\Core\Retention\Action;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategy;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPruner;
use App\Models\User;

ModelPruner::for(User::class)
    ->olderThan(years: 1)
    ->anonymize([
        'email'   => Strategy::hashedEmail(),          // anon_<hash>@anon.invalid
        'phone'   => Strategy::mask(keepLast: 4),      // *******1234
        'name'    => Strategy::fixed('Anonymized'),
        'address' => Strategy::null(),
        'notes'   => Strategy::closure(fn ($v) => substr((string) $v, 0, 3).'***'),
    ])
    ->clean();
```

**Built-in strategies:**

| Strategy | Factory | Description |
|---|---|---|
| `NullStrategy` | `Strategy::null()` | Sets the field to `null` |
| `FixedStrategy` | `Strategy::fixed($value)` | Replaces with a constant value |
| `HashedEmailStrategy` | `Strategy::hashedEmail($domain?)` | HMAC-derived unique placeholder email |
| `MaskStrategy` | `Strategy::mask($keepLast, $char)` | Masks all but the last N characters |
| `ClosureStrategy` | `Strategy::closure($fn)` / `Strategy::custom($fn)` | Fully custom closure |

### Retention Rules (v1.1.0)

Rules are composable and ANDed together. Multiple rules can be applied to the same pruner/cleaner.

| Rule | Description |
|---|---|
| `OlderThanRule` | Match rows where `column < now() - interval` |
| `WhereRule` | Arbitrary closure or Eloquent scope |
| `ExceptRule` | Wraps any rule with `whereNot()` — safety-hold exemption |
| `RetainUntil` | Retain rows matching a condition until a retention window expires |

### Cleanup Actions (v1.1.0)

Actions define what happens to each matched row. Use `Action::*` factory or construct directly.

| Action | `Action` factory | Description |
|---|---|---|
| `DeleteAction` | `Action::delete()` | Soft-delete (or hard-delete with `force: true`) |
| `DeleteAction` | `Action::forceDelete()` | Bypass soft-delete and permanently remove |
| `AnonymizeAction` | `Action::anonymize($fields)` | Per-field anonymization with idempotency guard |
| `NullifyAction` | `Action::nullify($columns)` | Set columns to `null` |
| `TransformAction` | `Action::transform($transforms)` | Apply arbitrary transformation closures |

### Safety Limit (v1.1.0)

Every `RuleBasedCleaner` and `ModelPruner` with `maxAffected()` enforces a hard cap. If matched rows exceed the limit the operation throws `SafetyLimitExceeded` **before** any changes are written.

Configure globally via `config/laravel-cleanup-data.php`:

```php
'retention' => [
    'max_affected' => env('DATA_CLEANUP_RETENTION_MAX_AFFECTED', 10_000),
    'chunk_size'   => env('DATA_CLEANUP_RETENTION_CHUNK_SIZE', 500),
    'marker_column' => env('DATA_CLEANUP_RETENTION_MARKER_COLUMN', 'anonymized_at'),
    'hash_domain'   => env('DATA_CLEANUP_RETENTION_HASH_DOMAIN', 'anon.invalid'),
],
```

Or per-operation:

```php
ModelPruner::for(User::class)
    ->olderThan(years: 2)
    ->action(Action::delete())
    ->maxAffected(2_000)
    ->chunkSize(200)
    ->clean();
```

## Configuration Reference

Refer to `config/laravel-cleanup-data.php` for complete configuration options including logging channel setup, failure reporting, schedule cron expression, disk folders, and retention settings.

## Contributing

Please feel free to submit issues or pull requests.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- **Ty Huynh** ([@ty-huynh](https://github.com/ty-huynh)) — [tyhuynh.id.vn](https://tyhuynh.id.vn)  
  15+ years · Senior Software Engineer (PHP/Laravel) & Technical Lead
