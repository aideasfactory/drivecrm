<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionVideo;
use Illuminate\Support\Collection;

class GetHazardPerceptionVideosForAdminAction
{
    /**
     * Return a flat list of all videos with their scoring zones for the
     * admin management screen.
     *
     * @return Collection<int, HazardPerceptionVideo>
     */
    public function __invoke(): Collection
    {
        return HazardPerceptionVideo::query()
            ->with('scoringZones')
            ->withCount('attempts')
            ->orderBy('category')
            ->orderBy('topic')
            ->orderBy('title')
            ->get();
    }
}
