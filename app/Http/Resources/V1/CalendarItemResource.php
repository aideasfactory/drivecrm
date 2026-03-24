<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CalendarItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendar_id' => $this->calendar_id,
            'date' => $this->whenLoaded('calendar', fn () => $this->calendar->date->format('Y-m-d')),
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
            'is_available' => $this->is_available,
            'status' => $this->status?->value ?? 'available',
            'item_type' => $this->item_type?->value ?? 'slot',
            'travel_time_minutes' => $this->travel_time_minutes,
            'parent_item_id' => $this->parent_item_id,
            'notes' => $this->notes,
            'unavailability_reason' => $this->unavailability_reason,
            'recurrence_pattern' => $this->recurrence_pattern?->value ?? 'none',
            'recurrence_end_date' => $this->recurrence_end_date?->format('Y-m-d'),
            'recurrence_group_id' => $this->recurrence_group_id,
        ];
    }
}
