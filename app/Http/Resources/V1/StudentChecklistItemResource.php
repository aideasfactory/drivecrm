<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentChecklistItemResource extends JsonResource
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
            'key' => $this->key,
            'label' => $this->label,
            'category' => $this->category,
            'is_checked' => $this->is_checked,
            'date' => $this->date?->toDateString(),
            'notes' => $this->notes,
            'sort_order' => $this->sort_order,
        ];
    }
}
