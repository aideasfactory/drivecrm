<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\HazardPerceptionVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HazardPerceptionTestResource extends JsonResource
{
    /**
     * With `videos` + `attempts` loaded (start / detail / submit responses)
     * this renders the full per-video breakdown; without them (history
     * listing) only the header fields are returned.
     *
     * `recap_video_url` is only revealed for videos the student has
     * completed within the test.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $relationsLoaded = $this->relationLoaded('videos') && $this->relationLoaded('attempts');

        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'total_videos' => $this->total_videos,
            'is_complete' => $this->completed_at !== null,
            'completed_videos' => $this->when($relationsLoaded, fn () => $this->attempts->count()),
            'total_score' => $relationsLoaded && $this->completed_at === null
                ? (int) $this->attempts->sum('total_score')
                : $this->total_score,
            'max_score' => $this->max_score,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'videos' => $this->when($relationsLoaded, fn () => $this->videoBreakdown()),
            'next_video' => $this->when($relationsLoaded && $this->completed_at === null, fn () => $this->nextVideo()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function videoBreakdown(): array
    {
        $attemptsByVideo = $this->attempts->keyBy('hazard_perception_video_id');

        return $this->videos->map(function (HazardPerceptionVideo $video) use ($attemptsByVideo): array {
            $attempt = $attemptsByVideo->get($video->id);

            return [
                'position' => $video->pivot->position,
                'max_score' => $video->is_double_hazard ? 10 : 5,
                'video' => new HazardPerceptionVideoResource($video),
                'attempt' => $attempt ? new HazardPerceptionAttemptResource($attempt) : null,
                'recap_video_url' => $attempt ? $video->recap_video_url : null,
            ];
        })->all();
    }

    protected function nextVideo(): ?HazardPerceptionVideoResource
    {
        $attemptedVideoIds = $this->attempts->pluck('hazard_perception_video_id');

        $next = $this->videos->first(
            fn (HazardPerceptionVideo $video): bool => ! $attemptedVideoIds->contains($video->id),
        );

        return $next ? new HazardPerceptionVideoResource($next) : null;
    }
}
