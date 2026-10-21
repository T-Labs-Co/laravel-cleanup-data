<?php

declare(strict_types=1);

namespace TLabsCo\LaravelCleanupData\Console\Commands;

use Illuminate\Console\Command;
use TLabsCo\LaravelCleanupData\Core\CleanupRegistry;
use TLabsCo\LaravelCleanupData\Core\Contracts\CleanerContract;
use TLabsCo\LaravelCleanupData\Core\Supports\CleanerTagsParser;

/**
 * List all registered cleanup actions
 *
 * Displays a table of all registered cleaners with their details
 */
class ListCleanersCommand extends Command
{
    protected $signature = 'cleanup:list
        {--tag= : Filter by tag}
        {--disabled : Show disabled cleaners}';

    protected $description = 'List all registered cleanup actions';

    public function handle(CleanupRegistry $registry, CleanerTagsParser $tagsParser): int
    {
        $cleaners = $registry->instances();

        if ($this->option('disabled')) {
            $cleaners = collect($registry->all())
                ->map(fn ($class) => app($class))
                ->filter(fn ($c) => ! $c->isEnabled());
        }

        $tagOption = $this->option('tag');
        if (is_string($tagOption) && $tagOption !== '') {
            $tags = $tagsParser->execute($tagOption);
            $cleaners = $cleaners->filter(
                fn ($c) => ! empty(array_intersect($tags, $c->getTags()))
            );
        }

        if ($cleaners->isEmpty()) {
            $this->warn('No cleaners found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Class', 'Tags', 'Enabled'],
            $cleaners->map(fn (CleanerContract $c) => [
                $c->getName(),
                $c::class,
                implode(', ', $c->getTags()),
                $c->isEnabled() ? '✓' : '✗',
            ])
        );

        $this->newLine();
        $this->info("Total: {$cleaners->count()} cleaners");

        return self::SUCCESS;
    }
}
