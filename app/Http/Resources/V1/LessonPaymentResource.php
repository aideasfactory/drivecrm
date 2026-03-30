<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'amount_pence' => $this->amount_pence,
            'status' => $this->status->value,
            'due_date' => $this->due_date?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'stripe_invoice_id' => $this->stripe_invoice_id,
        ];
    }
}
