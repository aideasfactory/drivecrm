<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Resource;
use Illuminate\Support\Collection;

class GetRecommendedResourcesAction
{
    /**
     * Find up to 5 resources whose tags match the AI-ranked tag list.
     *
     * Iterates through ranked tags (most relevant first) and collects
     * resources that contain each tag until 5 unique resources are found.
     *
     * @param  array<int, string>  $rankedTags  Tags ordered by relevance (from AI)
     * @param  int  $limit  Maximum number of resources to return
     * @return Collection<int, resource>
     */
    public function __invoke(array $rankedTags, int $limit = 5): Collection
    {
        if (empty($rankedTags)) {
            return collect();
        }

        $selectedIds = [];
        $resources = collect();

        foreach ($rankedTags as $tag) {
            if ($resources->count() >= $limit) {
                break;
            }

            // Find resources containing this tag (JSON array search)
            $matches = Resource::whereNotNull('tags')
                ->whereJsonContains('tags', strtolower($tag))
                ->when(! empty($selectedIds), fn ($q) => $q->whereNotIn('id', $selectedIds))
                ->limit($limit - $resources->count())
                ->get();

            foreach ($matches as $resource) {
                if (! in_array($resource->id, $selectedIds, true)) {
                    $selectedIds[] = $resource->id;
                    $resources->push($resource);
                }

                if ($resources->count() >= $limit) {
                    break;
                }
            }
        }

        return $resources;
    }
}
