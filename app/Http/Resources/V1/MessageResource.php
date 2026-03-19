<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the message into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->from,
            'sender_name' => $this->sender?->name,
            'recipient_id' => $this->to,
            'message' => $this->message,
            'is_own' => $this->from === $request->user()->id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
