<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HazardPerceptionAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hazard_perception_video_id' => $this->hazard_perception_video_id,
            'hazard_1_response_time' => $this->hazard_1_response_time,
            'hazard_1_score' => $this->hazard_1_score,
            'hazard_2_response_time' => $this->hazard_2_response_time,
            'hazard_2_score' => $this->hazard_2_score,
            'total_score' => $this->total_score,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'video' => new HazardPerceptionVideoResource($this->whenLoaded('video')),
        ];
    }
}
