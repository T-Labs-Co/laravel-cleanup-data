<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Strategies;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;

/**
 * Unique per row (derived from the primary key), so unique indexes keep working.
 */
final readonly class HashedEmailStrategy implements AnonymizationStrategy
{
    private string $domain;

    public function __construct(?string $domain = null)
    {
        $this->domain = $domain ?? (string) config('laravel-cleanup-data.retention.hash_domain', 'anon.invalid');
    }

    public function apply(mixed $value, Model $model, string $field): mixed
    {
        $hash = hash_hmac('sha256', (string) $model->getKey(), (string) config('app.key'));

        return 'anon_'.substr($hash, 0, 16).'@'.$this->domain;
    }

    public function label(): string
    {
        return 'hashed_email';
    }
}
