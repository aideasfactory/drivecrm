<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Resource;

class GetAllResourceTagsAction
{
    /**
     * Fetch all unique tags from every resource in the database.
     *
     * Resources store tags as a JSON array, e.g. ["roundabout", "right turn"].
     * This collects them all, deduplicates, and returns a flat array.
     *
     * @return array<int, string>
     */
    public function __invoke(): array
    {
        return Resource::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->map(fn (string $tag) => strtolower(trim($tag)))
            ->unique()
            ->values()
            ->all();
    }
}
