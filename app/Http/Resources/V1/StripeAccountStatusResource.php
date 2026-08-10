<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Instructor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stripe Connect account status for an Instructor.
 *
 * @mixin Instructor
 */
class StripeAccountStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'connected' => (bool) $this->stripe_account_id,
            'onboarding_complete' => (bool) $this->onboarding_complete,
            'charges_enabled' => (bool) $this->charges_enabled,
            'payouts_enabled' => (bool) $this->payouts_enabled,
        ];
    }
}
