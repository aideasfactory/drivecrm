<?php

namespace App\Models;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\RecurrencePattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalendarItem extends Model
{
    protected $table = 'calendar_items';

    protected $fillable = [
        'calendar_id',
        'start_time',
        'end_time',
        'is_available',
        'status',
        'item_type',
        'travel_time_minutes',
        'parent_item_id',
        'notes',
        'unavailability_reason',
        'recurrence_pattern',
        'recurrence_end_date',
        'recurrence_group_id',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'status' => CalendarItemStatus::class,
            'item_type' => CalendarItemType::class,
            'travel_time_minutes' => 'integer',
            'recurrence_pattern' => RecurrencePattern::class,
            'recurrence_end_date' => 'date',
        ];
    }

    /**
     * Check if this item is part of a recurring series.
     */
    public function isRecurring(): bool
    {
        return $this->recurrence_pattern !== null
            && $this->recurrence_pattern !== RecurrencePattern::None
            && $this->recurrence_group_id !== null;
    }

    /**
     * Check if this item is a travel-time block.
     */
    public function isTravel(): bool
    {
        return $this->item_type === CalendarItemType::Travel;
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /**
     * Get lessons scheduled in this calendar slot.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Get the parent slot item (for travel items linked to a lesson slot).
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    /**
     * Get the travel-time item linked to this slot.
     */
    public function travelItem(): HasOne
    {
        return $this->hasOne(self::class, 'parent_item_id');
    }

    /**
     * Convenience accessor for instructor via calendar.
     */
    public function getInstructorAttribute()
    {
        return $this->calendar->instructor;
    }
}
