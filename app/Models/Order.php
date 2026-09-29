<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'instructor_id',
        'package_id',
        'package_name',
        'package_total_price_pence',
        'package_lesson_price_pence',
        'package_lessons_count',
        'price_uplift_pence',
        'booking_fee_pence',
        'digital_fee_pence',
        'total_price_pence',
        'payment_mode',
        'status',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_subscription_id',
        'discount_code_id',
        'discount_percentage',
        'includes_test_pass_guarantee',
        'test_pass_guarantee_pence',
        'payment_hold_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_mode' => PaymentMode::class,
            'status' => OrderStatus::class,
            'package_total_price_pence' => 'integer',
            'package_lesson_price_pence' => 'integer',
            'package_lessons_count' => 'integer',
            'price_uplift_pence' => 'integer',
            'booking_fee_pence' => 'integer',
            'digital_fee_pence' => 'integer',
            'total_price_pence' => 'integer',
            'discount_percentage' => 'integer',
            'includes_test_pass_guarantee' => 'boolean',
            'test_pass_guarantee_pence' => 'integer',
            'payment_hold_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the student who enrolled.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * Get the assigned instructor.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Get the package.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the discount code used for this order.
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    /**
     * Get lessons for this order.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Get lesson payments for this order.
     */
    public function lessonPayments(): HasMany
    {
        return $this->hasManyThrough(LessonPayment::class, Lesson::class);
    }

    /**
     * The payment for the earliest lesson on a weekly order — the one taken at booking.
     */
    public function firstLessonPayment(): ?LessonPayment
    {
        return LessonPayment::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_payments.lesson_id')
            ->where('lessons.order_id', $this->id)
            ->orderBy('lessons.date')
            ->orderBy('lessons.start_time')
            ->orderBy('lesson_payments.id')
            ->select('lesson_payments.*')
            ->first();
    }

    /**
     * The first payment the student makes, worked out from the order snapshot so
     * it is available even after a released booking's payment records are gone:
     * the full total for upfront orders, or the first weekly instalment plus any
     * Pass Your Test Guarantee for weekly orders.
     */
    public function firstPaymentPence(): int
    {
        $totalPence = (int) ($this->total_price_pence ?? $this->package_total_price_pence ?? 0);

        if (! $this->isWeekly()) {
            return $totalPence;
        }

        $guaranteePence = $this->firstPaymentGuaranteePence();

        return LessonPayment::weeklyAmountForIndex($totalPence - $guaranteePence, (int) $this->package_lessons_count, 0)
            + $guaranteePence;
    }

    /**
     * The first payment itemised into lessons, booking fee, digital fee and any
     * Pass Your Test Guarantee. Uses the stored first weekly payment when there
     * is one, otherwise the order snapshot. The parts sum to `total_pence`.
     *
     * @return array{total_pence: int, lesson_pence: int, booking_fee_pence: int, digital_fee_pence: int, test_pass_guarantee_pence: int}
     */
    public function firstPaymentBreakdown(): array
    {
        $payment = $this->isWeekly() ? $this->firstLessonPayment() : null;

        $totalPence = $payment ? (int) $payment->amount_pence : $this->firstPaymentPence();
        $guaranteePence = $payment ? (int) $payment->test_pass_guarantee_pence : $this->firstPaymentGuaranteePence();

        $split = LessonPayment::weeklyBreakdown($this, $totalPence, $guaranteePence);

        return [
            'total_pence' => $totalPence,
            'lesson_pence' => $split['lesson'],
            'booking_fee_pence' => $split['booking_fee'],
            'digital_fee_pence' => $split['digital_fee'],
            'test_pass_guarantee_pence' => $split['test_pass_guarantee'] ?? 0,
        ];
    }

    /**
     * The Pass Your Test Guarantee charge included in the first payment.
     */
    public function firstPaymentGuaranteePence(): int
    {
        return $this->total_price_pence === null ? 0 : (int) ($this->test_pass_guarantee_pence ?? 0);
    }

    /**
     * Check if order is active.
     */
    public function isActive(): bool
    {
        return $this->status === OrderStatus::ACTIVE;
    }

    /**
     * Check if order is pending.
     */
    public function isPending(): bool
    {
        return $this->status === OrderStatus::PENDING;
    }

    /**
     * Whether the order is still waiting for its first payment and its slot
     * hold has not yet run out.
     */
    public function isAwaitingFirstPayment(): bool
    {
        return $this->isPending()
            && ! $this->isImported()
            && ! $this->hasPaymentHoldExpired();
    }

    /**
     * Whether the order had a slot hold that has now passed.
     */
    public function hasPaymentHoldExpired(): bool
    {
        return $this->payment_hold_expires_at !== null && $this->payment_hold_expires_at->isPast();
    }

    /**
     * Check if payment is upfront.
     */
    public function isUpfront(): bool
    {
        return $this->payment_mode === PaymentMode::UPFRONT;
    }

    /**
     * Check if payment is weekly.
     */
    public function isWeekly(): bool
    {
        return $this->payment_mode === PaymentMode::WEEKLY;
    }

    /**
     * Check if the order holds lessons imported from another system.
     * Imported orders have no Stripe payment and never create payouts.
     */
    public function isImported(): bool
    {
        return $this->payment_mode === PaymentMode::IMPORTED;
    }

    /**
     * Whether lessons on this order count as paid without a per-lesson
     * payment row: a confirmed upfront order, or an imported order (settled
     * outside the platform).
     */
    public function isPrepaid(): bool
    {
        return ($this->isUpfront() && $this->isActive()) || $this->isImported();
    }

    /**
     * Get formatted total price from snapshot (e.g., "£500.00").
     */
    public function getFormattedPackageTotalPriceAttribute(): string
    {
        return '£'.number_format(($this->package_total_price_pence ?? 0) / 100, 2);
    }

    /**
     * Get the actual amount charged for the order, including booking and digital
     * fees (e.g., "£612.50"). Mirrors the amount Stripe charges at checkout,
     * falling back to the package total for legacy orders without a stored total.
     */
    public function getFormattedAmountPaidAttribute(): string
    {
        $amountPence = $this->total_price_pence ?? $this->package_total_price_pence ?? 0;

        return '£'.number_format($amountPence / 100, 2);
    }

    /**
     * Get the formatted booking fee (e.g., "£9.99").
     */
    public function getFormattedBookingFeeAttribute(): string
    {
        return '£'.number_format(($this->booking_fee_pence ?? 0) / 100, 2);
    }

    /**
     * Get the formatted one-off digital fee (e.g., "£39.90").
     */
    public function getFormattedDigitalFeeAttribute(): string
    {
        return '£'.number_format(($this->digital_fee_pence ?? 0) / 100, 2);
    }

    /**
     * Get the formatted Pass Your Test Guarantee charge (e.g., "£50.00").
     */
    public function getFormattedTestPassGuaranteeAttribute(): string
    {
        return '£'.number_format(($this->test_pass_guarantee_pence ?? 0) / 100, 2);
    }

    /**
     * Whether the order carries anything on top of the lessons: a booking fee,
     * a digital fee or the Pass Your Test Guarantee (paid or included free).
     */
    public function hasFees(): bool
    {
        return ($this->booking_fee_pence ?? 0) > 0
            || ($this->digital_fee_pence ?? 0) > 0
            || (bool) $this->includes_test_pass_guarantee;
    }

    /**
     * Get the regular weekly instalment the student pays, including their share
     * of the booking and digital fees (e.g., "£65.99"). Any paid Pass Your Test
     * Guarantee is charged on top of the first instalment only, so it is left
     * out here. The last instalment absorbs any rounding remainder.
     */
    public function getFormattedWeeklyInstalmentAttribute(): string
    {
        $lessonsCount = (int) ($this->package_lessons_count ?? 0);
        $totalPence = (int) ($this->total_price_pence ?? $this->package_total_price_pence ?? 0)
            - (int) ($this->test_pass_guarantee_pence ?? 0);

        return '£'.number_format(LessonPayment::weeklyAmountForIndex($totalPence, $lessonsCount, 0) / 100, 2);
    }

    /**
     * Lines itemising the order cost for student-facing emails: the lessons,
     * each non-zero fee and the Pass Your Test Guarantee when included. The
     * total is left to the caller so each email can label it ("Total",
     * "Total paid").
     *
     * @return list<string>
     */
    public function costBreakdownLines(): array
    {
        $lines = ["Lessons: {$this->formatted_package_total_price}"];

        if ($this->booking_fee_pence > 0) {
            $lines[] = "Booking fee: {$this->formatted_booking_fee}";
        }

        if ($this->digital_fee_pence > 0) {
            $lines[] = "Digital fee: {$this->formatted_digital_fee}";
        }

        if ($this->includes_test_pass_guarantee) {
            $lines[] = $this->test_pass_guarantee_pence > 0
                ? "Pass Your Test Guarantee: {$this->formatted_test_pass_guarantee}"
                : 'Pass Your Test Guarantee: Included free';
        }

        return $lines;
    }

    /**
     * Get formatted lesson price from snapshot (e.g., "£50.00").
     */
    public function getFormattedPackageLessonPriceAttribute(): string
    {
        return '£'.number_format(($this->package_lesson_price_pence ?? 0) / 100, 2);
    }

    /**
     * Get formatted weekly payment from snapshot data.
     * Uses the stored lesson price, not the live package calculation.
     */
    public function getFormattedWeeklyPaymentAttribute(): string
    {
        if (! $this->package_lesson_price_pence || ! $this->package_lessons_count) {
            return '£0.00';
        }

        return '£'.number_format($this->package_lesson_price_pence / 100, 2);
    }
}
