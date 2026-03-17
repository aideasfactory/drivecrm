<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'profile' => $this->resolveProfile(),
        ];
    }

    /**
     * Resolve the role-specific profile resource.
     */
    private function resolveProfile(): InstructorProfileResource|StudentProfileResource|null
    {
        if ($this->relationLoaded('instructor') && $this->instructor) {
            return new InstructorProfileResource($this->instructor);
        }

        if ($this->relationLoaded('student') && $this->student) {
            return new StudentProfileResource($this->student);
        }

        return null;
    }
}
