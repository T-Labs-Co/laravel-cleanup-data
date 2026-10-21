# AI Agent Guidelines for `t-labs-co/laravel-cleanup-data`

This document provides guidelines, architectural context, and development instructions for AI coding agents (and human contributors) working on the `t-labs-co/laravel-cleanup-data` package.

---

## 1. Package Overview

- **Package Name:** `t-labs-co/laravel-cleanup-data`
- **Namespace:** `TLabsCo\LaravelCleanupData`
- **Description:** Centralized data-retention and cleanup framework for Laravel — prune Eloquent models, clean storage files, execute custom cleanup logic, and schedule operations automatically.
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
│   └── laravel-cleanup-data.php       # Package configuration (schedule, logging, cleaners, pruning)
├── src/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ListCleanersCommand.php # artisan cleanup:list
│   │       └── RunCleanupCommand.php  # artisan cleanup:run
│   ├── Core/
│   │   ├── Cleaners/
│   │   │   ├── BaseCleaner.php         # Abstract base cleaner with tag & config helpers
│   │   │   ├── ExpiredFilesCleaner.php # Storage files retention cleaner
│   │   │   └── GenericModelCleaner.php # Config-driven Eloquent model pruner
│   │   ├── Contracts/
│   │   │   ├── CleanerContract.php     # Interface for custom & core cleaners
│   │   │   └── CleanupHookContract.php # Interface for lifecycle hooks
│   │   ├── Hooks/
│   │   │   ├── AfterCleanupHook.php    # Lifecycle hook executed after cleanup
│   │   │   ├── BeforeCleanupHook.php   # Lifecycle hook executed before cleanup
│   │   │   └── LoggingHook.php         # Logger hook capturing execution stats
│   │   ├── Services/
│   │   │   ├── ModelPruner.php         # Fluent builder for Eloquent model pruning
│   │   │   └── ModelPrunerFactory.php  # Factory for building ModelPruner instances
│   │   ├── Supports/
│   │   │   ├── CleanerResolver.php     # Resolves cleaner classes or instances
│   │   │   ├── CleanerTagsParser.php   # Parses tag strings/arrays
│   │   │   └── StorageFilesService.php # Helper service for disk/directory file operations
│   │   ├── ValueObject/
│   │   │   └── CleanupResult.php       # Standardized immutable cleanup outcome VO
│   │   ├── CleanupRegistry.php         # Central registry for managing registered cleaners
│   │   └── CleanupRunner.php           # Execution engine coordinating hooks & cleaners
│   ├── Facades/
│   │   └── Cleaner.php                 # Static facade proxying to CleanupRunner / ModelPruner
│   ├── Providers/
│   │   └── LaravelCleanupDataServiceProvider.php # Package service provider & auto-discovery
│   └── Services/
│       └── Logger.php                  # Dedicated logger wrapper
└── tests/
    ├── Feature/                        # Integration and Artisan command tests
    ├── Unit/                           # Unit tests for core runners, registry, VOs, and pruners
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
- `ModelPruner` supports soft-delete pruning (`force: true` for `forceDelete()`, `force: false` for `delete()`), custom retention columns, chunking, and dry-run preview.
- Ensure queries properly handle timestamps and edge cases (e.g., negative/zero day intervals, custom date columns).

### 4.5 Configuration
- Main config file key is `laravel-cleanup-data` (not `data-cleanup`).
- Config publish tag is `laravel-cleanup-data-config`.
- Avoid hardcoded values in services — always use config defaults with sensible fallbacks.

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
