<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceFolderTreeResource extends JsonResource
{
    /**
     * The set of resource IDs suggested to this student.
     *
     * @var \Illuminate\Support\Collection<int, int>
     */
    public static $suggestedIds;

    /**
     * The set of resource IDs watched by this user.
     *
     * @var \Illuminate\Support\Collection<int, int>
     */
    public static $watchedIds;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => static::collection($this->whenLoaded('children')),
            'resources' => StudentResourceResource::collection($this->whenLoaded('resources')),
        ];
    }
}
