<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'instructor_id',
        'amount_pence',
        'date',
        'start_time',
        'end_time',
        'calendar_item_id',
        'completed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'completed_at' => 'datetime',
            'status' => LessonStatus::class,
        ];
    }

    /**
     * Get the order this lesson belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the instructor this lesson belongs to.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Get the lesson payment for this lesson.
     */
    public function lessonPayment(): HasOne
    {
        return $this->hasOne(LessonPayment::class);
    }

    /**
     * Get the payout for this lesson.
     */
    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    /**
     * Get the calendar item (time slot) for this lesson.
     */
    public function calendarItem(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class);
    }

    /**
     * Check if lesson is pending.
     */
    public function isPending(): bool
    {
        return $this->status === LessonStatus::PENDING;
    }

    /**
     * Check if lesson is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === LessonStatus::COMPLETED;
    }

    /**
     * Check if lesson has been paid for.
     */
    public function isPaid(): bool
    {
        return $this->lessonPayment && $this->lessonPayment->isPaid();
    }

    /**
     * Check if payout has been processed.
     */
    public function hasPayoutProcessed(): bool
    {
        return $this->payout !== null;
    }
}
