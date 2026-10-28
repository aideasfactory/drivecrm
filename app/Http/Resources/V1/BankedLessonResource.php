<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lesson kept in its booking to rebook later (no date until rebooked).
 *
 * @mixin Lesson
 */
class BankedLessonResource extends JsonResource
{
    /**
     * Expects `order.student`, `lessonPayment` and `bankedBy` to be loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $student = $this->order?->student;

        return [
            'id' => $this->id,
            'student_lesson_number' => $this->student_lesson_number,
            'order_id' => $this->order_id,
            'student_id' => $student?->id,
            'student_name' => $student ? trim($student->first_name.' '.$student->surname) : null,
            'package_name' => $this->order?->package_name,
            'amount_pence' => $this->amount_pence,
            'payment_mode' => $this->order?->payment_mode->value,
            'payment_status' => $this->lessonPayment?->status?->value ?? ($this->order?->isPrepaid() ? 'paid' : null),
            'banked_at' => $this->banked_at?->toISOString(),
            'expires_at' => $this->resource->bankedExpiresAt()?->toISOString(),
            'banked_reason' => $this->banked_reason,
            'banked_by_name' => $this->bankedBy?->name,
        ];
    }
}
