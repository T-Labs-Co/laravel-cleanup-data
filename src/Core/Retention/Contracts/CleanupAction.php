<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CleanupAction
{
    /** Apply the change. Return true if the row was actually changed. */
    public function execute(Model $model): bool;

    /**
     * Dry-run description — field NAMES and action labels only,
     * never original values (avoids leaking PII into logs).
     *
     * @return array<string, string> e.g. ['email' => 'hashed_email']
     */
    public function preview(Model $model): array;
}
