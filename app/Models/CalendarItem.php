<?php

namespace App\Models;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\RecurrencePattern;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalendarItem extends Model
{
    use HasFactory;

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
        'student_id',
        'notes',
        'unavailability_reason',
        'recurrence_pattern',
        'recurrence_end_date',
        'recurrence_group_id',
        'created_by_hold',
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
            'created_by_hold' => 'boolean',
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

    /**
     * Check if this item is a practical test slot.
     */
    public function isPracticalTest(): bool
    {
        return $this->item_type === CalendarItemType::PracticalTest;
    }

    /**
     * Check if this item is an open, unbooked availability slot.
     */
    public function isEmptyAvailability(): bool
    {
        return $this->item_type === CalendarItemType::Slot
            && $this->is_available
            && $this->status === null
            && ! $this->lessons()->exists();
    }

    /**
     * Check if a rescheduled booking may take over (consume) this item when
     * moved on top of it: an empty availability slot, or the travel block
     * belonging to one. Consumed items are deleted by the move.
     */
    public function isConsumableByReschedule(): bool
    {
        if ($this->isTravel()) {
            return (bool) $this->parentItem?->isEmptyAvailability();
        }

        return $this->isEmptyAvailability();
    }

    /**
     * Whether this item is already at the given date and times, i.e. an update
     * to these values would not move it.
     */
    public function isAt(string $date, string $startTime, string $endTime): bool
    {
        return $this->calendar?->date?->format('Y-m-d') === Carbon::parse($date)->format('Y-m-d')
            && Carbon::parse($this->start_time)->format('H:i') === Carbon::parse($startTime)->format('H:i')
            && Carbon::parse($this->end_time)->format('H:i') === Carbon::parse($endTime)->format('H:i');
    }

    /**
     * Items a new booking clashes with: another lesson (held, reserved, booked
     * or completed), a blocked-out period or a practical test. Open
     * availability is not a clash.
     *
     * @param  Builder<CalendarItem>  $query
     */
    public function scopeBlocksBooking(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [
                CalendarItemStatus::DRAFT,
                CalendarItemStatus::RESERVED,
                CalendarItemStatus::BOOKED,
                CalendarItemStatus::COMPLETED,
            ])
            ->orWhere('item_type', CalendarItemType::PracticalTest)
            ->orWhere(fn (Builder $query) => $query
                ->where('item_type', CalendarItemType::Slot)
                ->where('is_available', false)));
    }

    /**
     * Items whose time window overlaps the given one (touching ends don't overlap).
     *
     * @param  Builder<CalendarItem>  $query
     */
    public function scopeOverlapping(Builder $query, string $startTime, string $endTime): void
    {
        $query->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /**
     * Get the student assigned to this item (practical test slots).
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * Get lessons scheduled in this calendar slot.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Short-notice offer attached to this diary slot (at most one).
     */
    public function slotOffer(): HasOne
    {
        return $this->hasOne(SlotOffer::class);
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
