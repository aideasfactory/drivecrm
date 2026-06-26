<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CalendarItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lesson = $this->bookingLesson();

        return [
            'id' => $this->id,
            'date' => $this->whenLoaded('calendar', fn () => $this->calendar->date->format('Y-m-d')),
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
            'is_available' => $this->is_available,
            'status' => $this->status?->value,
            'item_type' => $this->item_type?->value ?? 'slot',
            'travel_time_minutes' => $this->travel_time_minutes,
            'parent_item_id' => $this->parent_item_id,
            'notes' => $this->notes,
            'unavailability_reason' => $this->unavailability_reason,
            'recurrence_pattern' => $this->recurrence_pattern?->value ?? 'none',
            'recurrence_end_date' => $this->recurrence_end_date?->format('Y-m-d'),
            'recurrence_group_id' => $this->recurrence_group_id,

            // Booking context (populated for booked/completed items when the lesson
            // relations are loaded — e.g. the calendar index endpoint). Drives the
            // status-dependent edit UI in the app: `future_siblings_count` powers the
            // "move just this / the whole booking" prompt; `is_paid` locks a paid
            // lesson down to reschedule-only.
            'lesson_id' => $lesson?->id,
            'order_id' => $lesson?->order_id,
            'student_name' => $this->studentName($lesson),
            'is_paid' => $this->isPaid($lesson),
            'amount_pence' => $lesson?->amount_pence,
            'mileage' => $lesson?->mileage,
            'future_siblings_count' => $this->futureSiblingsCount($lesson),
        ];
    }

    /**
     * The lesson backing a booked/completed slot, or null. Only resolved when the
     * `lessons` relation has been eager-loaded (e.g. the calendar index endpoint).
     */
    protected function bookingLesson(): ?Lesson
    {
        if (! $this->resource->relationLoaded('lessons')) {
            return null;
        }

        if ($this->status !== CalendarItemStatus::BOOKED && $this->status !== CalendarItemStatus::COMPLETED) {
            return null;
        }

        return $this->lessons->first();
    }

    protected function studentName(?Lesson $lesson): ?string
    {
        $student = $lesson?->order?->student;

        if (! $student) {
            return null;
        }

        return trim($student->first_name.' '.$student->surname);
    }

    protected function isPaid(?Lesson $lesson): ?bool
    {
        if (! $lesson) {
            return null;
        }

        return $lesson->lessonPayment?->isPaid()
            ?? ($lesson->order?->isUpfront() ? true : false);
    }

    /**
     * Count future un-signed-off lessons in the same order — the lessons a bulk
     * reschedule would carry along with this one.
     */
    protected function futureSiblingsCount(?Lesson $lesson): int
    {
        if (! $lesson || ! $lesson->order) {
            return 0;
        }

        return $lesson->order->lessons
            ->filter(fn (Lesson $sibling) => $sibling->id !== $lesson->id
                && $sibling->date?->gt($lesson->date)
                && $sibling->payout === null
                && $sibling->status !== LessonStatus::COMPLETED)
            ->count();
    }
}
