<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'instructor_id' => $this->instructor_id,
            'package_id' => $this->package_id,
            'package_name' => $this->package_name,
            'package_total_price_pence' => $this->package_total_price_pence,
            'package_lesson_price_pence' => $this->package_lesson_price_pence,
            'package_lessons_count' => $this->package_lessons_count,
            'booking_fee_pence' => $this->booking_fee_pence,
            'digital_fee_pence' => $this->digital_fee_pence,
            'total_price_pence' => $this->total_price_pence,
            'payment_mode' => $this->payment_mode->value,
            'status' => $this->status->value,
            'lessons_count' => $this->whenCounted('lessons', $this->lessons_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
