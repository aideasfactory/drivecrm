<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentHomeFeedResource extends JsonResource
{
    /**
     * Transform the student home feed payload into a JSON array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nextLesson = $this->resource['next_lesson'];
        $followingLesson = $this->resource['following_lesson'];

        return [
            'has_instructor' => $this->resource['has_instructor'],
            'next_lesson' => $nextLesson ? $this->formatLesson($nextLesson) : null,
            'following_lesson' => $followingLesson ? $this->formatLesson($followingLesson) : null,
            'special_offer' => $this->resource['special_offer'],
            'purchased_hours' => $this->resource['purchased_hours'],
            'learning_resources' => ResourceResource::collection($this->resource['learning_resources']),
            'instructor' => $this->resource['instructor'],
        ];
    }

    /**
     * Format a lesson model into a consistent array structure.
     *
     * @return array<string, mixed>
     */
    private function formatLesson($lesson): array
    {
        return [
            'id' => $lesson->id,
            'order_id' => $lesson->order_id,
            'instructor_id' => $lesson->instructor_id,
            'amount_pence' => $lesson->amount_pence,
            'date' => $lesson->date?->format('Y-m-d'),
            'start_time' => $lesson->start_time?->format('H:i'),
            'end_time' => $lesson->end_time?->format('H:i'),
            'status' => $lesson->status->value,
            'summary' => $lesson->summary,
        ];
    }
}
