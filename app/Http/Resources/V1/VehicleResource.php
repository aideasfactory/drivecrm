<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'registration' => $this->registration,
            'engine_size_cc' => $this->engine_size_cc,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'business_use_percentage' => (float) $this->business_use_percentage,
            'acquired_on' => $this->acquired_on?->format('Y-m-d'),
            'disposed_on' => $this->disposed_on?->format('Y-m-d'),
            'method_locked' => $this->methodLocked(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
