<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionVideo;
use Illuminate\Support\Collection;

class GetHazardPerceptionVideosAction
{
    /**
     * Return all hazard perception videos grouped by category then topic.
     *
     * @return Collection<string, Collection<string, Collection<int, HazardPerceptionVideo>>>
     */
    public function __invoke(?string $category = null): Collection
    {
        return HazardPerceptionVideo::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('topic')
            ->orderBy('title')
            ->get()
            ->groupBy(['category', 'topic']);
    }
}
