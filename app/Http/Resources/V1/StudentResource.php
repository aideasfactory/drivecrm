<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    protected bool $withoutContactDetails = false;

    /**
     * Leave out email, phone and date of birth, for a previous instructor who
     * only opens the pupil to sign off a lesson they taught.
     */
    public function withoutContactDetails(): static
    {
        $this->withoutContactDetails = true;

        return $this;
    }

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
            'email' => $this->withoutContactDetails ? null : ($this->email ?? $this->user?->email),
            'phone' => $this->withoutContactDetails ? null : $this->phone,
            'date_of_birth' => $this->withoutContactDetails ? null : $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'has_app' => $this->user_id !== null,
            'app_last_active_at' => $this->app_last_active_at?->toIso8601String(),
            'profile_picture_url' => $this->profile_picture_url,
            // Owner has turned on the alternative payment method (pays the instructor directly).
            'alternative_payment_allowed' => $this->cash_payments_enabled === true,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
