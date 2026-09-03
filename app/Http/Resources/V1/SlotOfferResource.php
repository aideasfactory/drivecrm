<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SlotOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'status' => $this->status?->value,
            'package' => $this->when(
                $this->relationLoaded('package') && $this->package,
                fn () => new PackageResource($this->package)
            ),
            'calendar_item' => $this->when(
                $this->relationLoaded('calendarItem') && $this->calendarItem,
                fn () => [
                    'id' => $this->calendarItem->id,
                    'date' => $this->calendarItem->relationLoaded('calendar')
                        ? $this->calendarItem->calendar?->date?->format('Y-m-d')
                        : null,
                    'start_time' => Carbon::parse($this->calendarItem->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($this->calendarItem->end_time)->format('H:i'),
                ]
            ),
            'booked_at' => $this->booked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
