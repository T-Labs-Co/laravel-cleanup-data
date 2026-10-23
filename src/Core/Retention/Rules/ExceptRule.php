<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\RetentionRule;

/** Safety exemption, e.g. legal hold. Always wins because it is ANDed in. */
final class ExceptRule implements RetentionRule
{
    public function __construct(private readonly RetentionRule $inner) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    public function apply(Builder $query): void
    {
        $query->whereNot(fn (Builder $q) => $this->inner->apply($q));
    }
}
