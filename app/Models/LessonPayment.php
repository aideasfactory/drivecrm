<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'amount_pence',
        'status',
        'due_date',
        'paid_at',
        'stripe_invoice_id',
        'stripe_charge_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'status' => PaymentStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the lesson this payment belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Check if payment is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    /**
     * Check if payment is due.
     */
    public function isDue(): bool
    {
        return $this->status === PaymentStatus::DUE;
    }

    /**
     * Check if payment is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::REFUNDED;
    }

    /**
     * Get formatted amount (e.g., "£50.00").
     */
    public function getFormattedAmountAttribute(): string
    {
        return '£'.number_format($this->amount_pence / 100, 2);
    }

    /**
     * Calculate a single weekly payment amount (in pence) for the lesson at the
     * given index, spreading the order total — base lesson cost plus booking and
     * digital fees — evenly across every lesson. Any rounding remainder lands on
     * the final payment so the sum of all payments equals the order total exactly.
     */
    public static function weeklyAmountForIndex(int $orderTotalPence, int $lessonsCount, int $index): int
    {
        if ($lessonsCount < 1) {
            return 0;
        }

        $perPaymentPence = (int) round($orderTotalPence / $lessonsCount);

        if ($index >= $lessonsCount - 1) {
            return $orderTotalPence - ($perPaymentPence * ($lessonsCount - 1));
        }

        return $perPaymentPence;
    }

    /**
     * The share of an order's fee-inclusive total attributable to the lesson at
     * `$index` (in date order). Used for upfront orders so each paid lesson
     * record reflects what the student actually paid, matching weekly orders.
     * Legacy orders without a stored total fall back to the lesson price.
     */
    public static function orderShareForLesson(Order $order, Lesson $lesson, int $index, int $lessonsCount): int
    {
        if ($order->total_price_pence === null) {
            return (int) $lesson->amount_pence;
        }

        return self::weeklyAmountForIndex((int) $order->total_price_pence, $lessonsCount, $index);
    }

    /**
     * Markdown lines itemising this payment (lesson cost, booking fee share,
     * digital fee share) for student-facing emails. Returns an empty array when
     * the order carries no fees.
     *
     * @return list<string>
     */
    public function costBreakdownLines(): array
    {
        $order = $this->lesson?->order;

        if (! $order) {
            return [];
        }

        return self::breakdownLines(self::weeklyBreakdown($order, (int) $this->amount_pence));
    }

    /**
     * Format a payment breakdown as markdown lines, ending with a blank line so
     * the amount line that follows in the template stays separated. Returns an
     * empty array when there are no fee components.
     *
     * @param  array{lesson?: int, booking_fee?: int, digital_fee?: int}  $breakdown
     * @return list<string>
     */
    public static function breakdownLines(array $breakdown): array
    {
        $lesson = (int) ($breakdown['lesson'] ?? 0);
        $bookingFee = (int) ($breakdown['booking_fee'] ?? 0);
        $digitalFee = (int) ($breakdown['digital_fee'] ?? 0);

        if ($bookingFee <= 0 && $digitalFee <= 0) {
            return [];
        }

        $lines = ['**Cost breakdown:**'];

        if ($lesson > 0) {
            $lines[] = 'Lesson cost: '.self::formatPence($lesson);
        }

        if ($bookingFee > 0) {
            $lines[] = 'Booking fee (weekly instalment): '.self::formatPence($bookingFee);
        }

        if ($digitalFee > 0) {
            $lines[] = 'Digital services fee (weekly instalment): '.self::formatPence($digitalFee);
        }

        $lines[] = '';

        return $lines;
    }

    protected static function formatPence(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    /**
     * Decompose a single weekly payment amount into its constituent cost
     * components — the lesson portion, the booking fee portion, and the
     * digital fee portion — based on the ratios stored on the order.
     *
     * The split is proportional to the order's own totals. The digital fee
     * component absorbs any rounding remainder so the three parts always
     * sum to $amountPence exactly. If the order has no meaningful totals
     * (legacy / zero-value), the entire amount is treated as the lesson
     * component.
     *
     * @return array{lesson: int, booking_fee: int, digital_fee: int}
     */
    public static function weeklyBreakdown(Order $order, int $amountPence): array
    {
        $packagePence = (int) ($order->package_total_price_pence ?? 0);
        $bookingPence = (int) ($order->booking_fee_pence ?? 0);
        $digitalPence = (int) ($order->digital_fee_pence ?? 0);
        $orderTotal = (int) ($order->total_price_pence ?? ($packagePence + $bookingPence + $digitalPence));

        if ($orderTotal <= 0 || $amountPence <= 0) {
            return [
                'lesson' => $amountPence,
                'booking_fee' => 0,
                'digital_fee' => 0,
            ];
        }

        $lessonComponent = (int) round($amountPence * ($packagePence / $orderTotal));
        $bookingComponent = (int) round($amountPence * ($bookingPence / $orderTotal));
        $digitalComponent = $amountPence - $lessonComponent - $bookingComponent;

        return [
            'lesson' => $lessonComponent,
            'booking_fee' => $bookingComponent,
            'digital_fee' => $digitalComponent,
        ];
    }
}
