<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $watchedIds = ResourceFolderTreeResource::$watchedIds;

        return [
            'id' => $this->resource_id,
            'title' => $this->resource_title,
            'resource_type' => $this->resource_type,
            'thumbnail_url' => $this->thumbnail_url,
            'folder_name' => $this->folder_name,
            'is_watched' => $watchedIds?->contains($this->resource_id) ?? false,
            'suggested_at' => $this->suggested_at,
        ];
    }
}
