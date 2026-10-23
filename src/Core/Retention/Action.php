<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Core\Retention;

use Closure;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\AnonymizeAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\DeleteAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\NullifyAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Actions\TransformAction;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\AnonymizationStrategy;
use TLabsCo\LaravelCleanupData\Core\Retention\Contracts\CleanupAction;

/**
 * Action enum & factory helpers.
 */
enum Action: string
{
    case Delete = 'delete';
    case ForceDelete = 'force_delete';
    case Anonymize = 'anonymize';
    case Nullify = 'nullify';
    case Transform = 'transform';

    public function toCleanupAction(): CleanupAction
    {
        return match ($this) {
            self::Delete => self::delete(),
            self::ForceDelete => self::forceDelete(),
            self::Anonymize => self::anonymize([]),
            self::Nullify => self::nullify([]),
            self::Transform => self::transform([]),
        };
    }

    public static function delete(bool $force = false): DeleteAction
    {
        return new DeleteAction($force);
    }

    public static function forceDelete(): DeleteAction
    {
        return new DeleteAction(true);
    }

    /**
     * @param  array<string, AnonymizationStrategy>  $fields
     */
    public static function anonymize(array $fields, ?string $markerColumn = null, bool $quietly = true): AnonymizeAction
    {
        return new AnonymizeAction($fields, $markerColumn, $quietly);
    }

    /**
     * @param  list<string>  $columns
     */
    public static function nullify(array $columns, bool $quietly = true): NullifyAction
    {
        return new NullifyAction($columns, $quietly);
    }

    /**
     * @param  array<string, Closure(mixed, Model): mixed>  $transforms
     */
    public static function transform(array $transforms, bool $quietly = true): TransformAction
    {
        return new TransformAction($transforms, $quietly);
    }
}
