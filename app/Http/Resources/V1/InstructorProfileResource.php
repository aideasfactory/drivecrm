<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorProfileResource extends JsonResource
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
            'bio' => $this->bio,
            'transmission_type' => $this->transmission_type,
            'status' => $this->status,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'onboarding_complete' => $this->onboarding_complete,
            'charges_enabled' => $this->charges_enabled,
            'payouts_enabled' => $this->payouts_enabled,
        ];
    }
}
