<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expects a Lesson model with eager-loaded relationships
     * and a computed card_status attribute.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'instructor_id' => $this->instructor_id,
            'instructor_name' => $this->instructor?->user?->name,
            'package_name' => $this->order?->package_name ?? $this->order?->package?->name,
            'amount_pence' => $this->amount_pence,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'status' => $this->status->value,
            'completed_at' => $this->completed_at?->toISOString(),
            'summary' => $this->summary,
            'payment_status' => $this->lessonPayment?->status?->value ?? ($this->order?->isUpfront() && $this->order?->isActive() ? 'paid' : null),
            'payment_mode' => $this->order?->payment_mode->value,
            'payout_status' => $this->payout?->status?->value,
            'has_payout' => $this->payout !== null,
            'calendar_date' => $this->calendarItem?->calendar?->date?->format('Y-m-d'),
            'card_status' => $this->getAttribute('card_status'),
            'has_reflective_log' => $this->reflectiveLog !== null,
            'reflective_log' => $this->reflectiveLog
                ? new ReflectiveLogResource($this->reflectiveLog)
                : null,
            'resources' => LessonResourceResource::collection($this->resources),
        ];
    }
}
