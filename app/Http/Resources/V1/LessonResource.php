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
            'order_id' => $this['order_id'],
            'instructor_name' => $this['instructor_name'],
            'instructor_avatar' => $this['instructor_avatar'],
            'package_name' => $this['package_name'],
            'date' => $this['date'],
            'start_time' => $this['start_time'],
            'end_time' => $this['end_time'],
            'status' => $this['status'],
            'completed_at' => $this['completed_at'],
            'card_status' => $this['card_status'],
            'has_reflective_log' => $this['has_reflective_log'],
            'resources_count' => $this['resources_count'],
            'payment_status' => $this['payment_status'],
        ];
    }
}
