<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PushNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
