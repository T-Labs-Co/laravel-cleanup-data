# Changelog

All notable changes to `t-labs-co/laravel-cleanup-data` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-23

### Added
- `RuleBasedCleaner` — new abstract base cleaner that composes `RetentionRule` objects with a `CleanupAction`; subclasses declare `model()`, `rules()`, and `action()` only
- **Retention Rule system** (`src/Core/Retention/Rules/`):
  - `OlderThanRule` — filter records older than a given number of days / months / years on any column
  - `WhereRule` — arbitrary Eloquent scope or closure as a retention rule
  - `ExceptRule` — safety-hold exemption; wraps any rule with `whereNot()`
  - `RetainUntil` — keep records matching a condition until they exceed a retention window
- **Cleanup Action system** (`src/Core/Retention/Actions/`):
  - `DeleteAction` — soft-delete or force-delete
  - `AnonymizeAction` — field-level anonymization with idempotency marker column (`anonymized_at`)
  - `NullifyAction` — set specified columns to `null`
  - `TransformAction` — arbitrary per-column transformation closures
- **`Action` enum** — fluent factory for all built-in actions (`Action::delete()`, `Action::anonymize(...)`, `Action::nullify(...)`, `Action::transform(...)`)
- **Anonymization Strategy system** (`src/Core/Retention/Strategies/`):
  - `NullStrategy` — replace value with `null`
  - `FixedStrategy` — replace with a fixed constant
  - `HashedEmailStrategy` — HMAC-derived unique placeholder email per row (preserves unique indexes)
  - `MaskStrategy` — mask characters, optionally keeping the last N characters visible
  - `ClosureStrategy` — fully custom closure strategy
  - `Strategy` factory — `Strategy::null()`, `Strategy::fixed()`, `Strategy::hashedEmail()`, `Strategy::mask()`, `Strategy::closure()` / `Strategy::custom()`
- **`CleanupRetentionEngine`** — `model()` / `batch()` helpers for composing and running multiple pruners
- **`SafetyLimitExceeded` exception** — thrown before any change when matched rows exceed `max_affected`
- **`ModelPruner` enhancements** — new fluent methods:
  - `.rule(RetentionRule)`, `.olderThan(days, months, years, column)`, `.where(...)`, `.except(...)`
  - `.scope(Closure)`, `.withTrashed()`, `.onlyTrashed()`
  - `.action(CleanupAction|Action|string)`, `.anonymize(fields)`, `.nullify(columns)`, `.transform(transforms)`
  - `.maxAffected(int)`, `.chunkSize(int)`
- **`retention` config block** in `laravel-cleanup-data.php`:
  - `max_affected` — hard cap (default 10 000) on records per run before safety abort
  - `chunk_size` — chunk size for batch processing (default 500)
  - `marker_column` / `anonymized_at_column` — idempotency marker for anonymization
  - `hash_domain` — domain used by `HashedEmailStrategy`
- **Migration stub** `2026_10_22_100001_add_anonymized_at_to_users_table.php` — example migration for the anonymization marker column

### Changed
- `ModelPruner` refactored to internally use `RetentionRule` and `CleanupAction` while preserving all existing `days()` / `column()` / `force()` / `clean()` / `dryRun()` behaviour

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
