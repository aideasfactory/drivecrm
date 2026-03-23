<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'total_price_pence' => $this->total_price_pence,
            'lessons_count' => $this->lessons_count,
            'lesson_price_pence' => $this->lesson_price_pence,
            'formatted_total_price' => $this->formatted_total_price,
            'formatted_lesson_price' => $this->formatted_lesson_price,
            'booking_fee' => $this->booking_fee,
            'digital_fee' => $this->digital_fee,
            'total_price' => $this->total_price,
            'weekly_payment' => $this->weekly_payment,
            'active' => $this->active,
            'has_stripe_price' => $this->stripe_price_id !== null,
        ];
    }
}
