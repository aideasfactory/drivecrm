<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expects the flat array structure returned by GetStudentLessonsAction.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'student_lesson_number' => $this['student_lesson_number'],
            'order_id' => $this['order_id'],
            'instructor_name' => $this['instructor_name'],
            'instructor_avatar' => $this['instructor_avatar'],
            'package_name' => $this['package_name'],
            'date' => $this['date'],
            'start_time' => $this['start_time'],
            'end_time' => $this['end_time'],
            'status' => $this['status'],
            'is_provisional' => $this['is_provisional'],
            'is_banked' => $this['is_banked'],
            'banked_at' => $this['banked_at'],
            'banked_expires_at' => $this['banked_expires_at'],
            'completed_at' => $this['completed_at'],
            'card_status' => $this['card_status'],
            'has_reflective_log' => $this['has_reflective_log'],
            'resources_count' => $this['resources_count'],
            'payment_status' => $this['payment_status'],
            'payment_mode' => $this['payment_mode'],
            'payment_breakdown' => $this['payment_breakdown'],
            'alternative_payment_allowed' => $this['alternative_payment_allowed'],
            'can_confirm_alternative_payment' => $this['can_confirm_alternative_payment'],
            'can_sign_off_with_alternative_payment' => $this['can_sign_off_with_alternative_payment'],
            'paid_by_alternative_method' => $this['paid_by_alternative_method'],
        ];
    }
}
