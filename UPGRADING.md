# Upgrading Guide

## Upgrading to 1.0.0 from `dbcsoft-labs/data-cleanup`

### Namespace Changes
Update namespace references from `DbcsoftLabs\DataCleanup` to `TLabsCo\LaravelCleanupData`:
- `DbcsoftLabs\DataCleanup\Providers\DataCleanupServiceProvider` &rarr; `TLabsCo\LaravelCleanupData\Providers\LaravelCleanupDataServiceProvider`
- `DbcsoftLabs\DataCleanup\Facades\Cleaner` &rarr; `TLabsCo\LaravelCleanupData\Facades\Cleaner`
- `DbcsoftLabs\DataCleanup\Core\*` &rarr; `TLabsCo\LaravelCleanupData\Core\*`

### Configuration Changes
- Configuration file renamed from `config/data-cleanup.php` to `config/laravel-cleanup-data.php`.
- Configuration keys renamed from `data-cleanup.*` to `laravel-cleanup-data.*`.
- Publish tag updated: `php artisan vendor:publish --tag=laravel-cleanup-data-config`.

### Scheduling
Scheduling is now handled via the `schedule` configuration block in `config/laravel-cleanup-data.php` or native Laravel scheduler without external attribute dependencies.
