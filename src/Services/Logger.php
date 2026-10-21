<?php

namespace TLabsCo\LaravelCleanupData\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final readonly class Logger
{
    public static function getLogger(): LoggerInterface
    {
        return Log::channel('cleanup');
    }
}
