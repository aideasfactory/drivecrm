<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorDayLessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expects a Lesson model with eager-loaded relationships
     * for the instructor day-view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $student = $order?->student;

        return [
            'id' => $this->id,
            'student_lesson_number' => $this->student_lesson_number,
            'order_id' => $this->order_id,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'status' => $this->status->value,
            'completed_at' => $this->completed_at?->toISOString(),
            'summary' => $this->summary,
            'amount_pence' => $this->amount_pence,
            'student' => $student ? [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'surname' => $student->surname,
                'email' => $student->email ?? $student->user?->email,
                'phone' => $student->phone,
                'status' => $student->status,
            ] : null,
            'package_name' => $order?->package_name,
            'payment_status' => $this->lessonPayment?->status?->value ?? ($order?->isUpfront() && $order?->isActive() ? 'paid' : null),
            'payment_mode' => $order?->payment_mode->value,
            'payout_status' => $this->payout?->status?->value,
            'has_payout' => $this->payout !== null,
            'calendar_item' => $this->calendarItem ? [
                'id' => $this->calendarItem->id,
                'start_time' => $this->calendarItem->start_time,
                'end_time' => $this->calendarItem->end_time,
                'status' => $this->calendarItem->status?->value,
                'item_type' => $this->calendarItem->item_type?->value,
                'notes' => $this->calendarItem->notes,
            ] : null,
            'has_reflective_log' => $this->reflectiveLog !== null,
            'resources_count' => $this->resources->count(),
        ];
    }
}
