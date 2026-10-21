# Changelog

All notable changes to `t-labs-co/laravel-cleanup-data` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-21

### Added
- `CleanupRegistry` — central cleaner registration with alias and tag support
- `CleanupRunner` — executes cleaners, propagates dry-run, collects results
- `BaseCleaner` — abstract base class with config-resolution and tag support
- `GenericModelCleaner` — config-driven model pruner (no custom class needed)
- `ExpiredFilesCleaner` — prune old files from storage directories
- `ModelPruner` — fluent builder for one-off model prune operations
- `Cleaner` facade — `Cleaner::model()->days()->clean()` API
- `cleanup:run` Artisan command with `--dry-run`, `--force`, `--tag`, `--cleaner`
- `cleanup:list` Artisan command with `--tag`, `--disabled` filters
- Built-in hook system (`BeforeCleanupHook`, `AfterCleanupHook`, `LoggingHook`)
- Scheduler config block — automatic cron scheduling without extra packages
- Laravel 12 & 13 support
- PHP 8.4 support
