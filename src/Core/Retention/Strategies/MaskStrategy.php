<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Strategies;

use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;

/** "0912345678" with keepLast=3 gives "*******678". */
final class MaskStrategy implements AnonymizationStrategy
{
    public function __construct(
        private readonly int $keepLast = 0,
        private readonly string $char = '*',
    ) {}

    public function apply(mixed $value, Model $model, string $field): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = (string) $value;
        $length = mb_strlen($value);
        $keep = min($this->keepLast, $length);

        return str_repeat($this->char, $length - $keep).mb_substr($value, $length - $keep);
    }

    public function label(): string
    {
        return 'mask';
    }
}
