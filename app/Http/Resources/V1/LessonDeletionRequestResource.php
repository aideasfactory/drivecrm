<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonDeletionRequestResource extends JsonResource
{
    /**
     * An instructor's request to delete a past lesson. `lesson_id` is null
     * once the request was approved and the lesson deleted.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'requested_at' => $this->requested_at?->toISOString(),
            'decided_at' => $this->decided_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
    }
}
