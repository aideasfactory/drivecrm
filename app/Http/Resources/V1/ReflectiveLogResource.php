<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReflectiveLogResource extends JsonResource
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
            'what_i_learned' => $this->what_i_learned,
            'what_went_well' => $this->what_went_well,
            'what_to_improve' => $this->what_to_improve,
            'additional_notes' => $this->additional_notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
