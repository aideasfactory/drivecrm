<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HazardPerceptionVideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'topic' => $this->topic,
            'video_url' => $this->video_url,
            'duration_seconds' => $this->duration_seconds,
            'is_double_hazard' => $this->is_double_hazard,
            'thumbnail_url' => $this->thumbnail_url,
        ];
    }
}
