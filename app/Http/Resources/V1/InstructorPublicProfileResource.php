<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorPublicProfileResource extends JsonResource
{
    /**
     * Transform the instructor into a student-facing public profile.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'bio' => $this->bio,
            'profile_picture_url' => $this->profile_picture_url,
        ];
    }
}
