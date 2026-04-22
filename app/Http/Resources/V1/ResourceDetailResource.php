<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceDetailResource extends JsonResource
{
    /**
     * Signed file URL (for file resources) or null.
     */
    public ?string $fileUrl = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags,
            'audience' => $this->audience?->value,
            'resource_type' => $this->resource_type,
            'video_url' => $this->video_url,
            'file_url' => $this->fileUrl,
            'file_name' => $this->file_name,
            'thumbnail_url' => $this->thumbnail_url,
        ];
    }
}
