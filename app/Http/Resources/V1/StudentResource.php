<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'first_name' => $this->first_name,
            'surname' => $this->surname,
            'email' => $this->email ?? $this->user?->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'has_app' => $this->user_id !== null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
