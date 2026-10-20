<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Payment\ConfirmAlternativePaymentBookingAction;
use App\Actions\Payment\GetConfirmableAlternativePaymentBookingsAction;
use App\Actions\Payment\RecordAlternativeLessonPaymentAction;
use App\Actions\Shared\LogActivityAction;
use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Support\AlternativePayments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Alternative payment method: the instructor confirms a pupil (whose
 * alternative payments an owner turned on) pays outside Stripe. Rules live in
 * {@see AlternativePayments}. Stripe is always stopped before anything is
 * recorded, so a pupil can't pay by card for a lesson recorded as paid
 * another way.
 */
class AlternativePaymentService extends BaseService
{
    public function __construct(
        protected OrderService $orderService,
        protected StripeService $stripeService,
        protected InstructorService $instructorService,
        protected ConfirmAlternativePaymentBookingAction $confirmBooking,
        protected RecordAlternativeLessonPaymentAction $recordLessonPayment,
        protected GetConfirmableAlternativePaymentBookingsAction $getConfirmableBookingsAction,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Confirm a weekly booking still awaiting its first payment as paid by an
     * alternative method. Closes the Stripe Checkout page, then activates the
     * order. If the page turns out to have been paid, the booking is confirmed
     * as a card booking instead and nothing else happens.
     *
     * @throws ValidationException When the booking can't be confirmed this way.
     */
    public function confirmBooking(Order $order, User $confirmedBy): Order
    {
        $order->loadMissing('student');

        if (! AlternativePayments::canConfirmBooking($order)) {
            throw ValidationException::withMessages([
                'order' => $this->bookingRefusalMessage($order),
            ]);
        }

        $checkout = $this->orderService->closeCheckoutForCancellation($order);

        if ($checkout === 'paid') {
            throw ValidationException::withMessages([
                'order' => 'The pupil has just paid for this booking by card, so it is already confirmed.',
            ]);
        }

        if ($checkout === 'failed') {
            throw ValidationException::withMessages([
                'order' => "We couldn't close the card payment page for this booking. Please try again in a minute.",
            ]);
        }

        if (! ($this->confirmBooking)($order, $confirmedBy)) {
            throw ValidationException::withMessages([
                'order' => 'This booking is no longer waiting for payment.',
            ]);
        }

        $this->logBookingConfirmed($order, $confirmedBy);

        if ($order->instructor) {
            $this->instructorService->invalidateStudentCache($order->instructor);
        }

        return $order->fresh(['lessons']);
    }

    /**
     * Get an unpaid lesson ready for sign-off by confirming the pupil paid by
     * an alternative method. Voids the lesson's open Stripe invoice first. If
     * the invoice turns out to have been paid, the payment is recorded as a
     * card payment and the sign-off goes ahead as a normal paid lesson.
     *
     * Call before the normal sign-off. Lessons that are already paid, or that
     * can't use the alternative method, are left alone so the normal sign-off
     * guards apply. An unpaid lesson that can use it needs the instructor's
     * confirmation that the pupil paid another way and they accept the risks.
     *
     * @return 'alternative'|'card'|'not_needed'
     *
     * @throws ValidationException When the confirmation is missing or the Stripe invoice can't be stopped.
     */
    public function prepareSignOff(Lesson $lesson, User $confirmedBy, bool $alternativePaymentConfirmed): string
    {
        $lesson->loadMissing(['order.student', 'lessonPayment']);

        if (! AlternativePayments::canSignOffWithAlternativePayment($lesson)) {
            return 'not_needed';
        }

        if (! $alternativePaymentConfirmed) {
            throw ValidationException::withMessages([
                'alternative_payment_confirmed' => "This lesson hasn't been paid by card. To sign it off, confirm the pupil paid you by an alternative method and that you accept the risks.",
            ]);
        }

        $payment = $lesson->lessonPayment;

        if ($payment->stripe_invoice_id) {
            $result = $this->stripeService->voidInvoice($payment->stripe_invoice_id);

            if ($result['status'] === 'paid') {
                $payment->update([
                    'status' => PaymentStatus::PAID,
                    'stripe_charge_id' => $result['charge_id'] ?? $payment->stripe_charge_id,
                    'paid_at' => now(),
                ]);

                Log::info('Invoice found paid at alternative sign-off — signing off as a card-paid lesson', [
                    'lesson_id' => $lesson->id,
                    'lesson_payment_id' => $payment->id,
                    'stripe_invoice_id' => $payment->stripe_invoice_id,
                ]);

                return 'card';
            }

            if ($result['status'] !== 'voided') {
                throw ValidationException::withMessages([
                    'alternative_payment_confirmed' => "We couldn't cancel the pupil's card payment request for this lesson. If they are paying by card right now, wait for it to go through. Otherwise try again in a minute.",
                ]);
            }
        }

        ($this->recordLessonPayment)($payment, $confirmedBy);

        Log::info('Lesson recorded as paid by alternative method', [
            'lesson_id' => $lesson->id,
            'lesson_payment_id' => $payment->id,
            'voided_invoice_id' => $payment->stripe_invoice_id,
            'confirmed_by_user_id' => $confirmedBy->id,
        ]);

        return 'alternative';
    }

    /**
     * The pupil's bookings still awaiting their first payment that can be
     * confirmed as paid by an alternative method, earliest lesson first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getConfirmableBookings(Student $student): Collection
    {
        return ($this->getConfirmableBookingsAction)($student);
    }

    protected function bookingRefusalMessage(Order $order): string
    {
        if (! AlternativePayments::allowedForStudent($order->student)) {
            return 'Alternative payments are not turned on for this pupil. Ask Head Office.';
        }

        if (! $order->isWeekly()) {
            return 'Only pay-as-you-go (weekly) bookings can be paid by an alternative method.';
        }

        if (! $order->isPending()) {
            return 'This booking is no longer waiting for payment.';
        }

        return 'The pupil booked this themselves, so it must be paid by card.';
    }

    protected function logBookingConfirmed(Order $order, User $confirmedBy): void
    {
        $student = $order->student;
        $metadata = [
            'order_id' => $order->id,
            'alternative_payment' => true,
            'confirmed_by_user_id' => $confirmedBy->id,
        ];
        $details = "{$order->package_name} ({$order->package_lessons_count} lessons)";

        if ($student) {
            ($this->logActivity)(
                $student,
                "Booking confirmed by {$confirmedBy->name}: pupil paying by an alternative method, risks accepted. {$details}",
                'booking',
                $metadata
            );
        }

        if ($order->instructor) {
            $studentName = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

            ($this->logActivity)(
                $order->instructor,
                "Booking confirmed by {$confirmedBy->name}: {$studentName} paying by an alternative method, risks accepted. {$details}",
                'booking',
                $metadata
            );
        }
    }
}
