<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'instructor_id' => $this->instructor_id,
            'amount_pence' => $this->amount_pence,
            'status' => $this->status->value,
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'lesson_payment' => $this->whenLoaded('lessonPayment', fn () => new LessonPaymentResource($this->lessonPayment)),
        ];
    }
}
