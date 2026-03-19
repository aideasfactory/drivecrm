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
            'latest_message' => [
                'id' => $this['latest_message']->id,
                'message' => $this['latest_message']->message,
                'is_own' => $this['latest_message']->from === $request->user()->id,
                'created_at' => $this['latest_message']->created_at?->toIso8601String(),
            ],
        ];
    }
}
