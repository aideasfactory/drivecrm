<?php

namespace App\Models;

use App\Enums\LessonCancellationReason;
use App\Enums\LessonStatus;
use App\Support\BookingPayments;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
        'cancellation_reason_code',
        'cancelled_at',
        'provisional_at',
        'banked_at',
        'banked_reason',
        'banked_by_user_id',
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
            'provisional_at' => 'datetime',
            'banked_at' => 'datetime',
            'status' => LessonStatus::class,
            'cancellation_reason_code' => LessonCancellationReason::class,
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
     * Get the user who took this lesson out of the diary to rebook later.
     */
    public function bankedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banked_by_user_id');
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
     * Whether this is a provisional lesson: booked as part of a package on a
     * week that clashed with something already in the diary, waiting for the
     * DRIVE team to rearrange it with the pupil.
     */
    public function isProvisional(): bool
    {
        return $this->provisional_at !== null
            && in_array($this->status, [LessonStatus::DRAFT, LessonStatus::PENDING], true);
    }

    /**
     * Open provisional lessons still sitting in the diary.
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopeProvisional(Builder $query): void
    {
        $query->whereNotNull('provisional_at')
            ->whereNotNull('calendar_item_id')
            ->whereIn('status', [LessonStatus::DRAFT, LessonStatus::PENDING]);
    }

    /**
     * Whether this lesson is banked: taken out of the diary to be rebooked
     * later. It stays in its booking at the same price and payment, with no
     * date or diary slot until the instructor rebooks it.
     */
    public function isBanked(): bool
    {
        return $this->banked_at !== null && $this->status === LessonStatus::PENDING;
    }

    /**
     * Whether this diary lesson can be taken out of the diary and kept to
     * rebook later: open, on a confirmed (active) booking, not paid out.
     */
    public function canBeBanked(): bool
    {
        return $this->status === LessonStatus::PENDING
            && $this->banked_at === null
            && $this->payout === null
            && $this->order?->isActive() === true;
    }

    /**
     * When a banked lesson is cancelled and refunded if it still hasn't been
     * rebooked (`lessons:expire-banked`). Null when not banked.
     */
    public function bankedExpiresAt(): ?CarbonInterface
    {
        return $this->isBanked()
            ? $this->banked_at->copy()->addMonthsNoOverflow(BookingPayments::bankedLessonExpiryMonths())
            : null;
    }

    /**
     * Open lessons waiting to be rebooked.
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopeBanked(Builder $query): void
    {
        $query->whereNotNull('banked_at')
            ->where('status', LessonStatus::PENDING);
    }

    /**
     * Check if lesson has been paid for.
     */
    public function isPaid(): bool
    {
        return $this->lessonPayment && $this->lessonPayment->isPaid();
    }

    /**
     * Whether cancelling this lesson now is a late cancellation (inside the
     * late-notice window). Flagged to Head Office when a paid lesson is cancelled.
     */
    public function isLateCancellation(): bool
    {
        if (! $this->date) {
            return false;
        }

        return BookingPayments::isLateCancellation(
            $this->date->toDateString(),
            $this->start_time?->format('H:i'),
        );
    }

    /**
     * Whether this lesson's start time (UK time) has been reached, so it can be
     * signed off. A lesson with no date has nothing to wait for, except a
     * banked one, which can't be signed off until it is rebooked.
     */
    public function hasStarted(): bool
    {
        if ($this->isBanked()) {
            return false;
        }

        if (! $this->date) {
            return true;
        }

        return BookingPayments::hasLessonStarted(
            $this->date->toDateString(),
            $this->start_time?->format('H:i'),
        );
    }

    /**
     * Whole hours of notice if this lesson were cancelled now; negative once it
     * has started, null when it has no date.
     */
    public function hoursNotice(): ?int
    {
        if (! $this->date) {
            return null;
        }

        return BookingPayments::hoursUntilLesson(
            $this->date->toDateString(),
            $this->start_time?->format('H:i'),
        );
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
     * lesson price alone. Lessons paid by an alternative method use their
     * payment (the lesson price, fees waived). Orders without a stored total
     * (legacy, imported) fall back to the lesson price.
     *
     * @return array{0: int, 1: int}
     */
    protected function studentPays(): array
    {
        $order = $this->order;
        $payment = $this->lessonPayment;

        if ($payment && ($order?->isUpfront() !== true || $payment->isPaidByAlternativeMethod() || (int) $payment->amount_pence > (int) $this->amount_pence)) {
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
