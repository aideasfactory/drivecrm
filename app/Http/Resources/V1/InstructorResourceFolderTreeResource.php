<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResourceFolderTreeResource extends JsonResource
{
    /**
     * Folder node for the instructor resource tree.
     *
     * Mirrors ResourceFolderTreeResource (student) but nests the full ResourceResource
     * (including `audience`, `video_url`, file metadata) and omits the student-only
     * `is_watched` / `is_suggested` flags.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => static::collection($this->whenLoaded('children')),
            'resources' => ResourceResource::collection($this->whenLoaded('resources')),
        ];
    }
}
