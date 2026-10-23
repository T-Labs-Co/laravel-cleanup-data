<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use TLabsCo\LaravelCleanupData\Facades\Cleaner;
use TLabsCo\LaravelCleanupData\Providers\LaravelCleanupDataServiceProvider;

abstract class TestCase extends Orchestra
{
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            LaravelCleanupDataServiceProvider::class,
        ];
    }

    #[\Override]
    protected function getPackageAliases($app): array
    {
        return [
            'Cleaner' => Cleaner::class,
        ];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    #[\Override]
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('test_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
