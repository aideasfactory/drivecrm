<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Support\AlternativePayments;
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
     * For the pupil, `summary` is only shown once the lesson is signed off and
     * never while it is still the one the importer brought over from the old
     * system's private diary notes, and the instructor's payout fields are
     * blanked.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPupil = $request->user()?->isStudent() === true;
        $isImported = $this->summary_from_import === true;

        return [
            'id' => $this->id,
            'student_lesson_number' => $this->student_lesson_number,
            'order_id' => $this->order_id,
            'instructor_id' => $this->instructor_id,
            'instructor_name' => $this->instructor?->user?->name,
            'package_name' => $this->order?->package_name ?? $this->order?->package?->name,
            'amount_pence' => $this->amount_pence,
            'payment_breakdown' => $this->resource->paymentBreakdown(),
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'status' => $this->status->value,
            'is_provisional' => $this->resource->isProvisional(),
            'is_banked' => $this->resource->isBanked(),
            'banked_at' => $this->banked_at?->toISOString(),
            'banked_expires_at' => $this->resource->bankedExpiresAt()?->toISOString(),
            'provisional_handled_by' => $this->resource->provisionalHandledBy()?->value,
            'completed_at' => $this->completed_at?->toISOString(),
            'summary' => $isPupil && ($isImported || ! $this->resource->isCompleted()) ? null : $this->summary,
            'is_imported' => $isImported,
            'payment_status' => $this->lessonPayment?->status?->value ?? ($this->order?->isPrepaid() ? 'paid' : null),
            'payment_mode' => $this->order?->payment_mode->value,
            ...AlternativePayments::lessonFlags($this->resource),
            'payout_status' => $isPupil ? null : $this->payout?->statusForInstructor()->value,
            'has_payout' => ! $isPupil && $this->payout !== null,
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
