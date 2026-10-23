<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Actions;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;

final class DeleteAction implements CleanupAction
{
    /** $force = true bypasses soft deletes. Keep false by default. */
    public function __construct(private readonly bool $force = false) {}

    public function execute(Model $model): bool
    {
        return (bool) ($this->force
            ? $model->forceDelete()
            : $model->delete());
    }

    public function preview(Model $model): array
    {
        return ['*' => $this->force ? 'force_delete' : 'delete'];
    }
}
