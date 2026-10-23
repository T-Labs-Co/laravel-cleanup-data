# AI Agent Guidelines for `t-labs-co/laravel-cleanup-data`

This document provides guidelines, architectural context, and development instructions for AI coding agents (and human contributors) working on the `t-labs-co/laravel-cleanup-data` package.

---

## 1. Package Overview

- **Package Name:** `t-labs-co/laravel-cleanup-data`
- **Namespace:** `TLabsCo\LaravelCleanupData`
- **Description:** GDPR-ready data retention, anonymization, and cleanup framework for Laravel — enforce retention policies, pseudonymize PII fields, prune Eloquent models, clean storage files, and schedule automated compliance operations.
- **License:** MIT
- **Authors:** Ty Huynh (<hongty.huynh@gmail.com>)

---

## 2. Environment & Requirements

- **PHP:** `^8.4` (requires strict types `declare(strict_types=1);` where applicable)
- **Laravel Framework:** `^12.0 || ^13.0`
- **Testing Framework:** Pest 3.x (`pestphp/pest`, `pestphp/pest-plugin-laravel`, `orchestra/testbench`)
- **Static Analysis:** PHPStan / Larastan at **Level 8** (`larastan/larastan`)
- **Code Formatter:** Laravel Pint (`laravel/pint`)

---

## 3. Directory & Architecture Map

```text
packages/t-labs-co/laravel-cleanup-data/
├── config/
│   └── laravel-cleanup-data.php       # Package configuration (schedule, logging, cleaners, pruning, retention)
├── src/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ListCleanersCommand.php # artisan cleanup:list
│   │       └── RunCleanupCommand.php  # artisan cleanup:run
│   ├── Core/
│   │   ├── Cleaners/
│   │   │   ├── BaseCleaner.php         # Abstract base cleaner with tag & config helpers
│   │   │   ├── ExpiredFilesCleaner.php # Storage files retention cleaner
│   │   │   ├── GenericModelCleaner.php # Config-driven Eloquent model pruner
│   │   │   └── RuleBasedCleaner.php    # Abstract cleaner that composes RetentionRule + CleanupAction
│   │   ├── Contracts/
│   │   │   ├── CleanerContract.php     # Interface for custom & core cleaners
│   │   │   └── CleanupHookContract.php # Interface for lifecycle hooks
│   │   ├── Hooks/
│   │   │   ├── AfterCleanupHook.php    # Lifecycle hook executed after cleanup
│   │   │   ├── BeforeCleanupHook.php   # Lifecycle hook executed before cleanup
│   │   │   └── LoggingHook.php         # Logger hook capturing execution stats
│   │   ├── Retention/
│   │   │   ├── Action.php              # Enum & factory for all built-in cleanup actions
│   │   │   ├── Strategy.php            # Fluent factory for anonymization strategies
│   │   │   ├── CleanupRetentionEngine.php # model()/batch() helpers for ModelPruner composition
│   │   │   ├── Actions/
│   │   │   │   ├── AnonymizeAction.php # Idempotent per-field anonymization
│   │   │   │   ├── DeleteAction.php    # Soft-delete or force-delete
│   │   │   │   ├── NullifyAction.php   # Set columns to null
│   │   │   │   └── TransformAction.php # Arbitrary per-column transformation closures
│   │   │   ├── Contracts/
│   │   │   │   ├── AnonymizationStrategy.php # Interface for anonymization strategies
│   │   │   │   ├── CleanupAction.php   # Interface for cleanup actions
│   │   │   │   └── RetentionRule.php   # Interface for retention rules
│   │   │   ├── Exceptions/
│   │   │   │   └── SafetyLimitExceeded.php # Thrown when matched rows exceed max_affected
│   │   │   ├── Rules/
│   │   │   │   ├── ExceptRule.php      # Wraps a rule with whereNot() — safety-hold exemption
│   │   │   │   ├── OlderThanRule.php   # Match rows older than days/months/years on a column
│   │   │   │   ├── RetainUntil.php     # Retain rows matching a condition until a window expires
│   │   │   │   └── WhereRule.php       # Arbitrary closure or Eloquent scope as a rule
│   │   │   └── Strategies/
│   │   │       ├── ClosureStrategy.php # Fully custom closure strategy
│   │   │       ├── FixedStrategy.php   # Replace with a fixed constant
│   │   │       ├── HashedEmailStrategy.php # HMAC-derived unique placeholder email
│   │   │       ├── MaskStrategy.php    # Mask characters, keeping the last N visible
│   │   │       ├── NullStrategy.php    # Set field to null
│   │   │       └── Strategy.php        # Base marker (strategies implement AnonymizationStrategy)
│   │   ├── Services/
│   │   │   ├── ModelPruner.php         # Fluent builder for Eloquent model pruning & retention
│   │   │   └── ModelPrunerFactory.php  # Factory for building ModelPruner instances
│   │   ├── Supports/
│   │   │   ├── CleanerResolver.php     # Resolves cleaner classes or instances
│   │   │   ├── CleanerTagsParser.php   # Parses tag strings/arrays
│   │   │   └── StorageFilesService.php # Helper service for disk/directory file operations
│   │   ├── ValueObject/
│   │   │   └── CleanupResult.php       # Standardized immutable cleanup outcome VO
│   │   ├── CleanupRegistry.php         # Central registry for managing registered cleaners
│   │   └── CleanupRunner.php           # Execution engine coordinating hooks & cleaners
│   ├── Databases/
│   │   └── Migrations/
│   │       └── 2026_10_22_100001_add_anonymized_at_to_users_table.php # Example migration for anonymization marker
│   ├── Facades/
│   │   └── Cleaner.php                 # Static facade proxying to CleanupRunner / ModelPruner
│   ├── Providers/
│   │   └── LaravelCleanupDataServiceProvider.php # Package service provider & auto-discovery
│   └── Services/
│       └── Logger.php                  # Dedicated logger wrapper
└── tests/
    ├── Feature/                        # Integration and Artisan command tests
    ├── Unit/                           # Unit tests for core runners, registry, VOs, and pruners
    │   └── Core/
    │       ├── AnonymizationStrategiesTest.php
    │       ├── RetentionActionsTest.php
    │       ├── RetentionRulesTest.php
    │       └── RuleBasedCleanerTest.php
    ├── Fixtures/                       # Test stub classes and dummy models
    ├── Pest.php                        # Pest setup and configuration
    └── TestCase.php                    # Base testbench TestCase configuring SQLite memory DB
```

---

## 4. Key Concepts & Coding Principles

### 4.1 Strict Typing & Quality Standards
- Always include `declare(strict_types=1);` at the top of PHP files.
- Specify explicit return types and parameter types for all methods and functions.
- Do not suppress PHPStan warnings unless absolutely necessary with explicit comments.
- Code must pass PHPStan Level 8 without errors.

### 4.2 Generic & Decoupled Framework
- This package is a **generic, standalone library** published to Packagist.
- **NEVER** introduce application-specific or domain-specific logic, models, or cleaners into this package (e.g., no stock, trading, notification, or business-domain models).
- Cleaners must either be generic utilities (`GenericModelCleaner`, `ExpiredFilesCleaner`) or user-defined in consuming applications.

### 4.3 Cleaners & Return Types
- All cleaners implement `CleanerContract` (or extend `BaseCleaner`).
- Cleaners must return a `CleanupResult` value object (`CleanupResult::success(...)` or `CleanupResult::failure(...)`).
- All cleaners must respect the `$dryRun` flag:
  - If `$dryRun` is `true`, simulate deletion and calculate affected count without actually deleting database rows or files.
  - Return `deleted: 0` or accurate counts depending on dry-run semantics.

### 4.4 Model Pruning
- `ModelPruner` supports soft-delete pruning (`force: true` for `forceDelete()`, `force: false` for `delete()`), custom retention columns, chunking, dry-run preview, and (v1.1.0) composable `RetentionRule` / `CleanupAction` API.
- In v1.1.0, `ModelPruner` gained: `.rule()`, `.olderThan()`, `.where()`, `.except()`, `.scope()`, `.withTrashed()`, `.onlyTrashed()`, `.action()`, `.anonymize()`, `.nullify()`, `.transform()`, `.maxAffected()`, `.chunkSize()`.
- Ensure queries properly handle timestamps and edge cases (e.g., negative/zero day intervals, custom date columns).

### 4.5 Retention Engine (v1.1.0)
- `RuleBasedCleaner` is the preferred base for new cleaners; it handles chunking, dry-run, safety limits, and logging automatically.
- Rules are ANDed in query order; the `ExceptRule` wraps any rule with `whereNot()` and is intended for legal-hold or safety-hold exemptions.
- `AnonymizeAction` is idempotent: records with a non-null value in the marker column (default `anonymized_at`) are skipped automatically.
- `SafetyLimitExceeded` is thrown **before** any row is written when matched count exceeds `max_affected`; always configure a reasonable limit.
- Anonymization strategies implement `AnonymizationStrategy`; use the `Strategy` factory (`Strategy::null()`, `Strategy::hashedEmail()`, etc.) for the built-in ones.
- The `Action` enum provides factory methods (`Action::delete()`, `Action::anonymize(...)`, etc.) for all built-in actions.

### 4.6 Configuration
- Main config file key is `laravel-cleanup-data` (not `data-cleanup`).
- Config publish tag is `laravel-cleanup-data-config`.
- Avoid hardcoded values in services — always use config defaults with sensible fallbacks.
- The `retention` config block (added in v1.1.0) controls `max_affected`, `chunk_size`, `marker_column`, and `hash_domain` for rule-based cleaners and anonymization.

---

## 5. Development & Verification Commands

When working within `packages/t-labs-co/laravel-cleanup-data`, run the following commands:

```bash
# Run test suite
vendor/bin/pest

# Run static analysis (PHPStan / Larastan Level 8)
vendor/bin/phpstan analyse --memory-limit=1G

# Run code style formatting (Laravel Pint)
vendor/bin/pint
```

---

## 6. Testing Guidelines

- Tests use **Pest PHP** syntax (`it(...)` or `test(...)`).
- Feature tests extending `TLabsCo\LaravelCleanupData\Tests\TestCase` have access to an in-memory SQLite database.
- When creating tests that test database pruning, use `TLabsCo\LaravelCleanupData\Tests\Fixtures\TestModel`.
- Always verify both standard execution and `--dry-run` modes for any command or cleaner logic.
- For `RuleBasedCleaner` tests, verify the safety-limit abort path (`SafetyLimitExceeded`) as well as successful execution and dry-run paths.
- For `AnonymizeAction` tests, verify idempotency: running the action twice on the same record must not change the record on the second pass.
