<?php

namespace App\Models;

use App\Enums\CalendarItemStatus;
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
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'status' => CalendarItemStatus::class,
        ];
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
