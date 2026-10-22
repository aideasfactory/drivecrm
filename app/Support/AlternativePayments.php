<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LessonStatus;
use App\Enums\OrderSource;
use App\Enums\PaymentMode;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;

/**
 * Rules for the alternative payment method: a pupil paying the instructor
 * outside Stripe (e.g. cash) on an otherwise normal card booking.
 *
 * The only gate is the owner's switch on the pupil
 * (`students.cash_payments_enabled`, shown as "Alternative payment method").
 * Bookings stay card bookings: the pupil is invoiced and reminded as usual
 * and can pay by card at any time. The instructor can confirm a booking that
 * is still awaiting its first payment, and sign off an unpaid lesson by
 * confirming the pupil paid another way. Weekly (pay-as-you-go) and pay-in-full
 * orders: confirming a pay-in-full booking records every lesson as paid by the
 * alternative method there and then, since the pupil pays the whole block up
 * front.
 */
final class AlternativePayments
{
    /**
     * Sources where the learner booked and paid there and then. Those keep the
     * normal short payment hold and can't be confirmed by the instructor.
     *
     * @var list<OrderSource>
     */
    private const LEARNER_SOURCES = [OrderSource::WEBSITE, OrderSource::LEARNER_APP];

    /**
     * Payment modes that can use the alternative payment method.
     *
     * @var list<PaymentMode>
     */
    private const PAYMENT_MODES = [PaymentMode::WEEKLY, PaymentMode::UPFRONT];

    /**
     * The payment modes that can use the alternative method as stored values,
     * for queries.
     *
     * @return list<string>
     */
    public static function paymentModeValues(): array
    {
        return array_map(fn (PaymentMode $mode): string => $mode->value, self::PAYMENT_MODES);
    }

    /**
     * The learner self-booking sources as stored values, for queries.
     *
     * @return list<string>
     */
    public static function learnerSourceValues(): array
    {
        return array_map(fn (OrderSource $source): string => $source->value, self::LEARNER_SOURCES);
    }

    /**
     * Whether an owner has turned on alternative payments for the pupil.
     */
    public static function allowedForStudent(?Student $student): bool
    {
        return $student?->cash_payments_enabled === true;
    }

    /**
     * Whether the order can use the alternative payment method at all.
     */
    public static function allowedForOrder(?Order $order): bool
    {
        return $order !== null
            && in_array($order->payment_mode, self::PAYMENT_MODES, true)
            && self::allowedForStudent($order->student);
    }

    /**
     * Whether the order was booked by staff or the instructor, rather than by
     * the learner paying at checkout. A missing source counts as staff.
     */
    public static function isStaffOrInstructorBooking(Order $order): bool
    {
        return ! in_array($order->source, self::LEARNER_SOURCES, true);
    }

    /**
     * Whether the instructor can confirm this booking, still awaiting its first
     * payment, as paid by an alternative method. This activates the order.
     */
    public static function canConfirmBooking(?Order $order): bool
    {
        return self::allowedForOrder($order)
            && $order->isPending()
            && self::isStaffOrInstructorBooking($order);
    }

    /**
     * Whether this lesson can be signed off by confirming the pupil paid by an
     * alternative method: a pending lesson on an active weekly order whose
     * payment has not been made.
     */
    public static function canSignOffWithAlternativePayment(?Lesson $lesson): bool
    {
        if (! $lesson || $lesson->status !== LessonStatus::PENDING) {
            return false;
        }

        $order = $lesson->order;

        if (! self::allowedForOrder($order) || ! $order->isActive()) {
            return false;
        }

        $payment = $lesson->lessonPayment;

        return $payment !== null && $payment->isDue();
    }

    /**
     * What the instructor can do about the lesson's payment, for API and web
     * responses. All null when there is no lesson. Needs `order.student` and
     * `lessonPayment` loaded to avoid extra queries.
     *
     * @return array{alternative_payment_allowed: bool|null, can_confirm_alternative_payment: bool|null, can_sign_off_with_alternative_payment: bool|null, paid_by_alternative_method: bool|null}
     */
    public static function lessonFlags(?Lesson $lesson): array
    {
        if (! $lesson) {
            return [
                'alternative_payment_allowed' => null,
                'can_confirm_alternative_payment' => null,
                'can_sign_off_with_alternative_payment' => null,
                'paid_by_alternative_method' => null,
            ];
        }

        return [
            'alternative_payment_allowed' => self::allowedForOrder($lesson->order),
            'can_confirm_alternative_payment' => self::canConfirmBooking($lesson->order),
            'can_sign_off_with_alternative_payment' => self::canSignOffWithAlternativePayment($lesson),
            'paid_by_alternative_method' => $lesson->lessonPayment?->isPaidByAlternativeMethod() === true,
        ];
    }
}
