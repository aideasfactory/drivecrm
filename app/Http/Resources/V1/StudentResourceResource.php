<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $suggestedIds = ResourceFolderTreeResource::$suggestedIds;
        $watchedIds = ResourceFolderTreeResource::$watchedIds;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'resource_type' => $this->resource_type,
            'thumbnail_url' => $this->thumbnail_url,
            'tags' => $this->tags,
            'is_suggested' => $suggestedIds?->contains($this->id) ?? false,
            'is_watched' => $watchedIds?->contains($this->id) ?? false,
        ];
    }
}
