<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'summary',
        'cancellation_reason',
        'cancelled_at',
        'mileage',
        'student_lesson_number',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * Get the reflective log for this lesson.
     */
    public function reflectiveLog(): HasOne
    {
        return $this->hasOne(ReflectiveLog::class);
    }

    /**
     * Get the resources attached to this lesson.
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class)
            ->withTimestamps()
            ->orderBy('resources.sort_order')
            ->orderBy('resources.title');
    }

    /**
     * Get the scheduled reminders that have been sent for this lesson.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(LessonReminder::class);
    }

    /**
     * Check if lesson is a draft (pre-payment).
     */
    public function isDraft(): bool
    {
        return $this->status === LessonStatus::DRAFT;
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
     * Check if lesson has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === LessonStatus::CANCELLED;
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

    /**
     * What the student pays for this lesson, itemised into the lesson cost,
     * booking fee share, digital fee share and any Pass Your Test Guarantee
     * charged with it (only ever on an order's first lesson). The parts always
     * sum to `total_pence`. Expects `order` and `lessonPayment` to be loaded.
     *
     * @return array{total_pence: int, lesson_pence: int, booking_fee_pence: int, digital_fee_pence: int, test_pass_guarantee_pence: int}
     */
    public function paymentBreakdown(): array
    {
        [$totalPence, $guaranteePence] = $this->studentPays();

        if (! $this->order) {
            return [
                'total_pence' => $totalPence,
                'lesson_pence' => $totalPence,
                'booking_fee_pence' => 0,
                'digital_fee_pence' => 0,
                'test_pass_guarantee_pence' => 0,
            ];
        }

        $split = LessonPayment::weeklyBreakdown($this->order, $totalPence, $guaranteePence);

        return [
            'total_pence' => $totalPence,
            'lesson_pence' => $split['lesson'],
            'booking_fee_pence' => $split['booking_fee'],
            'digital_fee_pence' => $split['digital_fee'],
            'test_pass_guarantee_pence' => $split['test_pass_guarantee'] ?? 0,
        ];
    }

    /**
     * The fee-inclusive amount the student pays for this lesson, and the part
     * of it that is the Pass Your Test Guarantee. Weekly lessons use their
     * instalment. Upfront lessons use their share of the order total, since
     * upfront payment records created before fees were apportioned hold the
     * lesson price alone. Orders without a stored total (legacy, imported)
     * fall back to the lesson price.
     *
     * @return array{0: int, 1: int}
     */
    protected function studentPays(): array
    {
        $order = $this->order;
        $payment = $this->lessonPayment;

        if ($payment && ($order?->isUpfront() !== true || (int) $payment->amount_pence > (int) $this->amount_pence)) {
            return [(int) $payment->amount_pence, (int) $payment->test_pass_guarantee_pence];
        }

        $spreadTotalPence = (int) ($order?->total_price_pence ?? 0) - (int) ($order?->test_pass_guarantee_pence ?? 0);
        $lessonsCount = (int) ($order?->package_lessons_count ?? 0);

        if ($spreadTotalPence <= 0 || $lessonsCount < 1) {
            return [(int) $this->amount_pence, 0];
        }

        // Legacy upfront record: the lesson's index is unknown here, so the
        // even share is used and any guarantee stays on the order itself.
        return [LessonPayment::weeklyAmountForIndex($spreadTotalPence, $lessonsCount, 0), 0];
    }
}
