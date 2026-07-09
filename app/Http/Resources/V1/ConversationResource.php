<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the conversation into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this['user']->id,
                'name' => $this['user']->name,
            ],
            'student_id' => $this['student_id'] ?? null,
            'latest_message' => [
                'id' => $this['latest_message']->id,
                'message' => $this['latest_message']->message,
                'is_own' => $this['latest_message']->from === $request->user()->id,
                'is_read' => $this['latest_message']->isRead(),
                'created_at' => $this['latest_message']->created_at?->toIso8601String(),
            ],
            'unread_count' => $this['unread_count'] ?? 0,
        ];
    }
}
