<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A freshly minted Stripe Connect onboarding Account Link.
 *
 * @property array{url: string, stripe_account_id: string} $resource
 */
class StripeOnboardingLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource['url'],
            'stripe_account_id' => $this->resource['stripe_account_id'],
        ];
    }
}
