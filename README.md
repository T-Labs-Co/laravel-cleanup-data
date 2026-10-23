# Laravel Cleanup Data

[![Tests](https://github.com/t-labs-co/laravel-cleanup-data/actions/workflows/tests.yml/badge.svg)](https://github.com/t-labs-co/laravel-cleanup-data/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/t-labs-co/laravel-cleanup-data.svg)](https://packagist.org/packages/t-labs-co/laravel-cleanup-data)
[![License](https://img.shields.io/packagist/l/t-labs-co/laravel-cleanup-data.svg)](https://packagist.org/packages/t-labs-co/laravel-cleanup-data)
[![PHP](https://img.shields.io/packagist/php-v/t-labs-co/laravel-cleanup-data.svg)](https://packagist.org/packages/t-labs-co/laravel-cleanup-data)

    GDPR-ready data retention, anonymization, and cleanup framework for Laravel.
    Enforce data retention policies, pseudonymize personal data (PII), prune Eloquent models, clean storage files, and schedule automated compliance operations — all from a single config.

## Features

- 🔒 **GDPR Compliance**: Built-in tools for data retention policies, right-to-be-forgotten, and data minimization principles.
- 🎭 **Data Anonymization** *(v1.1.0)*: Pseudonymize PII fields with idempotent, field-level strategies — hashed emails, masked values, nullification, fixed replacements, and custom closures.
- 📋 **Retention Rules Engine** *(v1.1.0)*: Compose declarative retention rules (`OlderThan`, `Where`, `Except`, `RetainUntil`) with any cleanup action — no boilerplate query code required.
- 🛡️ **Safety Limits** *(v1.1.0)*: Hard caps per run — aborts before touching data when matched rows exceed `max_affected`, preventing accidental mass operations.
- 🚀 **Centralized Cleanup Registry**: Register and execute cleanup actions with tag and alias support.
- 🧹 **Generic Model Pruner**: Prune records from Eloquent models based on date/time columns via config or fluent builder.
- 📁 **Expired Files Cleaner**: Clean up old files from storage disks and directories automatically.
- ⚡ **Fluent API & Facade**: Convenient `Cleaner::model(User::class)->days(30)->clean()` API for ad-hoc cleanup.
- 🔍 **Dry-Run Mode**: Safely preview items that would be deleted or anonymized without touching real data.
- ⏰ **Built-in Scheduling**: Config-driven automated cron scheduling for recurring compliance tasks.
- 🪝 **Hook & Logging System**: Lifecycle hooks (`before`, `after`, logging) for comprehensive audit trails and error tracking.

## Requirements

- PHP 8.4+
- Laravel 12.0+ or 13.0+

## GDPR & Privacy Compliance

This package provides the building blocks to implement key GDPR requirements:

### Data Retention

Enforce data minimization by automatically pruning or anonymizing data that exceeds retention periods:

```php
// Anonymize user PII after 1 year of inactivity
ModelPruner::for(User::class)
    ->olderThan(years: 1)
    ->anonymize([
        'email' => Strategy::hashedEmail(),
        'phone' => Strategy::mask(keepLast: 4),
        'name'  => Strategy::fixed('Anonymized User'),
    ])
    ->clean();
```

### Right to be Forgotten

Support data subject deletion requests with safe, auditable cleanup operations:

```php
// Delete all data for a specific user across multiple models
CleanupRetentionEngine::batch([
    ModelPruner::for(User::class)->where('id', $userId)->action(Action::delete()),
    ModelPruner::for(Order::class)->where('user_id', $userId)->action(Action::delete()),
    ModelPruner::for(ActivityLog::class)->where('user_id', $userId)->action(Action::delete()),
])->clean();
```

### Pseudonymization

Replace identifiable fields with irreversible placeholders while preserving data utility for analytics:

```php
// Pseudonymize customer data for analytics while preserving unique identifiers
ModelPruner::for(Customer::class)
    ->olderThan(months: 24)
    ->anonymize([
        'email'    => Strategy::hashedEmail(),      // anon_<hmac>@anon.invalid (preserves uniqueness)
        'name'     => Strategy::null(),              // Remove entirely
        'ip_addr'  => Strategy::mask(keepLast: 2),   // Mask IP, keep last 2 octets
        'notes'    => Strategy::closure(fn ($v) => '[redacted]'),
    ])
    ->clean();
```

### Safety Guarantees

- **Idempotent anonymization**: Records with `anonymized_at` set are skipped on subsequent runs.
- **Safety limits**: Operations abort before any changes if matched rows exceed `max_affected`.
- **Dry-run preview**: Always test cleanup scopes before execution with `--dry-run`.
- **Audit logging**: All operations are logged with matched counts, changed counts, and samples.

### Compliance Feature Mapping

| GDPR Requirement | Package Feature | Implementation |
|---|---|---|
| Data Minimization (Art. 5) | Retention Rules | `OlderThanRule`, `RetainUntil` |
| Right to Erasure (Art. 17) | Delete Actions | `Action::delete()`, `Action::forceDelete()` |
| Pseudonymization (Art. 4) | Anonymization | `Strategy::hashedEmail()`, `Strategy::mask()` |
| Purpose Limitation (Art. 5) | Tag-based Cleaners | Tag cleaners by purpose (`analytics`, `marketing`) |
| Data Portability (Art. 20) | Dry-run Mode | Preview data before export or deletion |
| Accountability (Art. 5) | Logging Hooks | `LoggingHook` provides audit trails |

## Installation

```bash
composer require t-labs-co/laravel-cleanup-data
```

Publish configuration file:
```bash
php artisan vendor:publish --tag=laravel-cleanup-data-config
```

Publish migrations, if apply idempotent anonymization for users with GDPR compliance:

```bash
php artisan vendor:publish --tag=laravel-cleanup-data-migrations
php artisan migrate
```

## Quick Start (3 minutes)

### 1. Data Anonymization (GDPR)

Pseudonymize personal data while preserving data structure:

```php
use TLabsCo\LaravelCleanupData\Facades\Cleaner;
use TLabsCo\LaravelCleanupData\Core\Retention\Strategy;
use App\Models\User;

// Anonymize inactive users' PII fields
$result = Cleaner::model(User::class)
    ->olderThan(years: 1)
    ->anonymize([
        'email' => Strategy::hashedEmail(),
        'phone' => Strategy::mask(keepLast: 4),
        'name'  => Strategy::fixed('Anonymized User'),
    ])
    ->clean();
```

### 2. Fluent Facade Pruning

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

### 3. Rule-Based Pruning

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

### 4. Config-Driven Model Pruning

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

### 5. Artisan Commands

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

### Database Migration for Anonymization

To enable idempotent anonymization, add the marker column to your tables:

```bash
php artisan vendor:publish --tag=laravel-cleanup-data-migrations
php artisan migrate
```

Or create your own migration:

```php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('anonymized_at')->nullable()->after('updated_at');
});
```

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `DATA_CLEANUP_ENABLED` | `true` | Master switch for all cleanup operations |
| `DATA_CLEANUP_RETENTION_MAX_AFFECTED` | `10000` | Safety limit: max rows per run |
| `DATA_CLEANUP_RETENTION_CHUNK_SIZE` | `500` | Batch chunk size for memory efficiency |
| `DATA_CLEANUP_RETENTION_MARKER_COLUMN` | `anonymized_at` | Idempotency marker column name |
| `DATA_CLEANUP_RETENTION_HASH_DOMAIN` | `anon.invalid` | Domain for hashed email strategy |
| `DATA_CLEANUP_SCHEDULE_ENABLED` | `false` | Enable automatic cron scheduling |
| `DATA_CLEANUP_SCHEDULE_EXPRESSION` | `0 2 * * *` | Cron expression (default: 2 AM daily) |
| `DATA_CLEANUP_LOGGING_ENABLED` | `true` | Enable detailed cleanup logging |
| `DATA_CLEANUP_LOG_LEVEL` | `info` | Log level for cleanup operations |

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

## Quality & Development Practices

This package is developed following Laravel best practices, with a strong focus on reliability, maintainability, and code quality.

- 🧩 **Laravel Best Practices:** Follows established Laravel conventions and recommended design principles.
- ✅ **Comprehensive Test Coverage:** Features are covered by unit and integration tests using Pest PHP.
- 🔍 **Static Analysis:** Validated with PHPStan Level 8 using Larastan to catch potential type errors and enforce strict typing.
- 🤖 **AI-Assisted Development:** AI assistants support development, analysis, and code quality improvements.
- 👨‍💻 **Senior Engineer Review:** All changes are carefully reviewed by a senior engineer before each release.

## Credits

- **Ty Huynh** ([@ty-huynh](https://github.com/ty-huynh)) — [tyhuynh.id.vn](https://tyhuynh.id.vn)  
  15+ years · Senior Software Engineer (PHP/Laravel) & Technical Lead
