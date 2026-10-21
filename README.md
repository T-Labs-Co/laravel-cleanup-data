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

### 2. Config-Driven Model Pruning

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

### 3. Artisan Commands

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

## Configuration Reference

Refer to `config/laravel-cleanup-data.php` for complete configuration options including logging channel setup, failure reporting, schedule cron expression, and disk folders.

## Contributing

Please feel free to submit issues or pull requests.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- **Ty Huynh** ([@ty-huynh](https://github.com/ty-huynh)) — [tyhuynh.id.vn](https://tyhuynh.id.vn)  
  15+ years · Senior Software Engineer (PHP/Laravel) & Technical Lead
