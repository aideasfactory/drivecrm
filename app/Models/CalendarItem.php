<?php

namespace App\Models;

use App\Enums\CalendarItemStatus;
use App\Enums\RecurrencePattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarItem extends Model
{
    protected $table = 'calendar_items';

    protected $fillable = [
        'calendar_id',
        'start_time',
        'end_time',
        'is_available',
        'status',
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
     * Convenience accessor for instructor via calendar.
     */
    public function getInstructorAttribute()
    {
        return $this->calendar->instructor;
    }
}
