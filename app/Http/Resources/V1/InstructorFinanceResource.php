<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorFinanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'category_label' => $this->category_label,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'description' => $this->description,
            'amount_pence' => $this->amount_pence,
            'formatted_amount' => $this->formatted_amount,
            'is_recurring' => $this->is_recurring,
            'recurrence_frequency' => $this->recurrence_frequency,
            'date' => $this->date?->format('Y-m-d'),
            'notes' => $this->notes,
            'receipt' => $this->receipt_path ? [
                'url' => $this->receipt_url,
                'original_name' => $this->receipt_original_name,
                'mime_type' => $this->receipt_mime_type,
                'size_bytes' => $this->receipt_size_bytes,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
