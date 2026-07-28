<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountDeletionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'requested_at' => $this->requested_at?->toISOString(),
            'scheduled_for' => $this->scheduled_for?->toISOString(),
        ];
    }
}
