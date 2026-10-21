<?php

namespace TLabsCo\LaravelCleanupData\Core\Supports;

final class CleanerTagsParser
{
    /**
     * @param  string|array<string>  $tags
     * @return array<string>
     */
    public function execute(string|array $tags): array
    {
        $tagList = is_array($tags) ? $tags : (preg_split('/[,;:|]/', $tags) ?: []);

        return collect($tagList)
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
