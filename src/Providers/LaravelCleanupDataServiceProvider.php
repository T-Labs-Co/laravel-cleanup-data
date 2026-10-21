<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use TLabsCo\LaravelCleanupData\Console\Commands\ListCleanersCommand;
use TLabsCo\LaravelCleanupData\Console\Commands\RunCleanupCommand;
use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Core\CleanupRunner;
use TLabsCo\LaravelCleanupData\Core\Services\ModelPrunerFactory;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerResolver;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerTagsParser;

/**
 * Laravel Cleanup Data Service Provider
 *
 * Registers the data cleanup system and its components
 */
class LaravelCleanupDataServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    #[\Override]
    public function register(): void
    {
        // Merge config from package
        $this->mergeConfigFrom(
            __DIR__.'/../../config/laravel-cleanup-data.php',
            'laravel-cleanup-data'
        );

        // Register log channel
        $this->registerLogChannel();

        // Register Tag Parser
        $this->app->singleton(CleanerTagsParser::class);

        // Register ModelPrunerFactory as singleton
        $this->app->singleton(ModelPrunerFactory::class);

        // Register CleanupRegistry as singleton
        $this->app->singleton(CleanupRegistry::class, fn ($app) => new CleanupRegistry($app->make(CleanerTagsParser::class)));

        // Register CleanerResolver as singleton
        $this->app->singleton(CleanerResolver::class, fn ($app) => new CleanerResolver($app->make(CleanupRegistry::class)));

        // Register CleanupRunner as singleton
        $this->app->singleton(CleanupRunner::class, fn ($app) => new CleanupRunner(
            $app->make(CleanupRegistry::class),
            $app->make(CleanerResolver::class)
        ));
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish config and register schedule in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/laravel-cleanup-data.php' => config_path('laravel-cleanup-data.php'),
            ], 'laravel-cleanup-data-config');

            $this->registerSchedule();
        }

        // Register commands
        $this->commands([
            RunCleanupCommand::class,
            ListCleanersCommand::class,
        ]);

        // Register default cleaners
        $this->registerDefaultCleaners();
    }

    private function registerSchedule(): void
    {
        /** @var array<string, mixed> $scheduleConfig */
        $scheduleConfig = (array) config('laravel-cleanup-data.schedule', []);
        if (empty($scheduleConfig['enabled'])) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($scheduleConfig): void {
            $cmd = $schedule->command('cleanup:run --force')
                ->cron((string) ($scheduleConfig['expression'] ?? '0 2 * * *'));

            if (! empty($scheduleConfig['without_overlapping'])) {
                $cmd->withoutOverlapping();
            }
        });
    }

    private function registerLogChannel(): void
    {
        /** @var array<string, mixed> $loggingConfig */
        $loggingConfig = (array) config('laravel-cleanup-data.logging', []);

        /** @var Repository $config */
        $config = $this->app->make('config');
        $config->set('logging.channels.cleanup', $loggingConfig);
    }

    /**
     * Register default cleaners
     */
    protected function registerDefaultCleaners(): void
    {
        $registry = $this->app->make(CleanupRegistry::class);
        $registry->boot();
    }
}
