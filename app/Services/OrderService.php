<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Calendar\CloseOpenSlotOffersForItemsAction;
use App\Actions\Calendar\DetectCalendarClashesAction;
use App\Actions\Onboarding\SendOrderConfirmationEmailAction;
use App\Actions\Payment\SendLessonInvoiceAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Student\GrantTestPassGuaranteeAction;
use App\Actions\Student\Order\ConfirmWeeklyFirstPaymentAction;
use App\Actions\Student\Order\CreateDraftCalendarItemsAction;
use App\Actions\Student\Order\CreateOrderFromApiAction;
use App\Actions\Student\Order\ReleaseUnpaidOrderAction;
use App\Actions\Student\Order\SendPaymentLinkEmailAction;
use App\Actions\Student\Order\VerifyCheckoutAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Notifications\CalendarClashDetectedNotification;
use App\Support\BookingPayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;

class OrderService extends BaseService
{
    public function __construct(
        protected CreateDraftCalendarItemsAction $createDraftCalendarItems,
        protected CreateOrderFromApiAction $createOrderFromApi,
        protected VerifyCheckoutAction $verifyCheckout,
        protected SendOrderConfirmationEmailAction $sendConfirmationEmail,
        protected SendPaymentLinkEmailAction $sendPaymentLinkEmail,
        protected StripeService $stripeService,
        protected DetectCalendarClashesAction $detectCalendarClashes,
        protected LogActivityAction $logActivity,
        protected InstructorService $instructorService,
        protected SendLessonInvoiceAction $sendLessonInvoice,
        protected CloseOpenSlotOffersForItemsAction $closeOpenSlotOffersForItems,
        protected PushNotificationService $pushNotificationService,
        protected ConfirmWeeklyFirstPaymentAction $confirmWeeklyFirstPaymentAction,
        protected ReleaseUnpaidOrderAction $releaseUnpaidOrderAction,
        protected GrantTestPassGuaranteeAction $grantTestPassGuarantee,
    ) {}

    /**
     * Book lessons: create draft calendar items, order and lessons, then take the
     * first payment. Nothing is confirmed until that payment lands.
     *
     * When $returnCheckoutUrl is true (student-initiated mobile bookings), the Stripe
     * checkout URL is returned so the app can open it in an in-app browser, and the
     * slots are held for the short learner window. When false (instructor or admin
     * diary bookings), a payment link is emailed and the slots are held until the
     * first payment is due (48 hours before the first lesson).
     *
     * Pay in full charges the whole order; pay weekly charges the first week.
     *
     * @return array{order: Order, checkout_url?: string|null}
     */
    public function bookLessons(
        Student $student,
        Package $package,
        PaymentMode $paymentMode,
        string $firstLessonDate,
        string $startTime,
        string $endTime,
        bool $returnCheckoutUrl = false,
        ?int $anchorCalendarItemId = null
    ): array {
        $calendarItemIds = ($this->createDraftCalendarItems)(
            $student->instructor_id,
            $firstLessonDate,
            $startTime,
            $endTime,
            $package->lessons_count,
            $anchorCalendarItemId
        );

        $this->checkDraftItemClashes($student->instructor_id, $calendarItemIds, $startTime, $endTime);

        $order = ($this->createOrderFromApi)(
            $student,
            $package,
            $paymentMode,
            $firstLessonDate,
            $startTime,
            $endTime,
            $calendarItemIds
        );

        $order->update([
            'payment_hold_expires_at' => $returnCheckoutUrl
                ? BookingPayments::learnerHoldExpiresAt()
                : BookingPayments::instructorHoldExpiresAt($firstLessonDate, $startTime),
        ]);

        $checkoutUrl = null;

        if ($returnCheckoutUrl) {
            $checkoutUrl = $this->createCheckoutSession($order, $package, $student, 'mobile_app');
        } else {
            $this->sendPaymentLinkEmail->execute($order, $student, $this->paymentLinkUrl($order, 'instructor_booking'));
        }

        // Invalidate grouped students cache so the instructor sees the new booking immediately
        $this->invalidateStudentCacheForBooking($student->instructor_id);

        ($this->closeOpenSlotOffersForItems)($calendarItemIds);

        return [
            'order' => $order->fresh(['lessons']),
            'checkout_url' => $returnCheckoutUrl ? $checkoutUrl : null,
        ];
    }

    /**
     * Book lessons using a specific open diary slot as the first lesson.
     *
     * @return array{order: Order, checkout_url?: string|null}
     */
    public function bookLessonsFromCalendarItem(
        Student $student,
        Package $package,
        PaymentMode $paymentMode,
        CalendarItem $calendarItem,
        bool $returnCheckoutUrl = false
    ): array {
        $calendarItem->loadMissing('calendar');

        if (! $student->instructor_id || $calendarItem->calendar->instructor_id !== $student->instructor_id) {
            throw ValidationException::withMessages([
                'calendar_item_id' => 'This diary slot does not belong to the student\'s instructor.',
            ]);
        }

        if (! $calendarItem->isEmptyAvailability()) {
            throw ValidationException::withMessages([
                'calendar_item_id' => 'This diary slot is no longer available.',
            ]);
        }

        $date = $calendarItem->calendar->date->format('Y-m-d');
        $startTime = Carbon::parse($calendarItem->start_time)->format('H:i');
        $endTime = Carbon::parse($calendarItem->end_time)->format('H:i');

        return $this->bookLessons(
            $student,
            $package,
            $paymentMode,
            $date,
            $startTime,
            $endTime,
            $returnCheckoutUrl,
            $calendarItem->id
        );
    }

    /**
     * Create a Stripe Checkout session for the order's first payment (the full
     * amount, or the first week for weekly orders).
     */
    protected function createCheckoutSession(Order $order, Package $package, Student $student, string $bookingSource): ?string
    {
        $user = $student->user;

        if (! $user->stripe_customer_id) {
            $customerResult = $this->stripeService->createOrGetCustomer($user);

            if (! $customerResult['success']) {
                Log::error('Failed to create Stripe customer for API order', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'error' => $customerResult['error'] ?? 'Unknown',
                ]);

                return null;
            }

            $user->stripe_customer_id = $customerResult['customer_id'];
            $user->save();
        }

        // Route Stripe's redirect back to unauthenticated web pages that verify
        // the session and render a human-facing confirmation. The previous API
        // URLs forced the student onto the login page because they were Sanctum
        // protected. The mobile in-app browser can still detect these URLs by
        // path to close the webview after payment if needed.
        $successUrl = route('payment-link.checkout.success', ['order' => $order->id]).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('payment-link.checkout.cancel', ['order' => $order->id]);

        $result = $this->stripeService->createCheckoutSession(
            $order,
            $package,
            $user,
            $order->instructor,
            $successUrl,
            $cancelUrl,
            $bookingSource
        );

        if ($result['success']) {
            $order->stripe_checkout_session_id = $result['session_id'];
            $order->save();

            return $result['url'];
        }

        Log::error('Failed to create Stripe checkout session for API order', [
            'order_id' => $order->id,
            'error' => $result['error'] ?? 'Unknown',
        ]);

        return null;
    }

    /**
     * Check each newly created draft calendar item for clashes and notify the instructor.
     *
     * @param  array<int, int>  $calendarItemIds
     */
    protected function checkDraftItemClashes(int $instructorId, array $calendarItemIds, string $startTime, string $endTime): void
    {
        $instructor = Instructor::with('user')->find($instructorId);

        if (! $instructor) {
            return;
        }

        foreach ($calendarItemIds as $itemId) {
            $item = CalendarItem::with('calendar')->find($itemId);

            if (! $item || ! $item->calendar) {
                continue;
            }

            $date = $item->calendar->date->format('Y-m-d');
            $clashes = ($this->detectCalendarClashes)($instructor, $date, $startTime, $endTime, $item->id);

            if ($clashes->isNotEmpty()) {
                $instructor->user->notify(new CalendarClashDetectedNotification($item, $clashes, $instructor));

                ($this->logActivity)(
                    $instructor,
                    'Scheduling clash detected on '.Carbon::parse($date)->format('j M Y').' at '.$startTime.' — '.$clashes->count().' conflicting item(s)',
                    'notification',
                    [
                        'new_item_id' => $item->id,
                        'clashing_item_ids' => $clashes->pluck('id')->toArray(),
                        'date' => $date,
                    ]
                );
            }
        }
    }

    /**
     * Re-send the payment-link email for an order still awaiting its first payment.
     *
     * The hold is not extended. Also queues an additive push to the learner,
     * mirroring the weekly payment-reminder behaviour (only when the learner
     * owns their account and has an Expo push token).
     *
     * @return array{email: string}
     *
     * @throws ValidationException When the order is not awaiting payment or no link can be sent.
     */
    public function resendPaymentLink(Order $order, Student $student): array
    {
        return $this->sendPaymentLink($order, $student, 'payment_link_resend');
    }

    /**
     * Email the payment link for an order still awaiting its first payment
     * (the full amount, or the first week for weekly orders).
     *
     * The email links to our own signed page, which opens a Stripe Checkout
     * session when clicked. That lets the link outlive Stripe's 24-hour session
     * limit and stop working the moment the hold runs out.
     *
     * @return array{email: string}
     *
     * @throws ValidationException When the order is not awaiting payment or no link can be sent.
     */
    public function sendPaymentLink(Order $order, Student $student, string $bookingSource, bool $isBookedByStaff = false): array
    {
        $this->ensureOrderAwaitingPayment($order);

        if (! $order->package) {
            throw ValidationException::withMessages([
                'order' => 'The package for this order is no longer available, so a payment link cannot be generated.',
            ]);
        }

        $checkoutUrl = $this->paymentLinkUrl($order, $bookingSource);

        $recipientEmail = $this->sendPaymentLinkEmail->execute($order, $student, $checkoutUrl, $isBookedByStaff);

        if (! $recipientEmail) {
            throw ValidationException::withMessages([
                'order' => 'No email address is on file for this student, so the payment link could not be sent.',
            ]);
        }

        if (! $isBookedByStaff && $student->owns_account && $student->user?->expo_push_token) {
            $this->pushNotificationService->queueIfHasToken(
                $student->user,
                'Payment link re-sent',
                'Your lesson payment link has been re-sent — tap to pay',
                [
                    'type' => 'payment_link_resent',
                    'order_id' => $order->id,
                    'checkout_url' => $checkoutUrl,
                ],
            );
        }

        return ['email' => $recipientEmail];
    }

    /**
     * Open the Stripe Checkout page behind an emailed payment link.
     *
     * Reuses the order's open session when there is one, otherwise creates a
     * fresh one that expires no later than the hold.
     *
     * @return array{status: 'checkout', url: string}|array{status: 'paid'|'unavailable'|'error', message: string}
     */
    public function openPaymentLinkCheckout(Order $order, string $bookingSource): array
    {
        if ($order->isActive() || $order->status === OrderStatus::COMPLETED) {
            return ['status' => 'paid', 'message' => 'This booking has already been paid for.'];
        }

        if (! $order->isAwaitingFirstPayment()) {
            return ['status' => 'unavailable', 'message' => 'The time to pay for this booking has passed, so the lessons have been released.'];
        }

        $package = $order->package;
        $student = $order->student;

        if (! $package || ! $student) {
            return ['status' => 'unavailable', 'message' => 'This booking can no longer be paid for.'];
        }

        $checkoutUrl = $this->resolveOpenCheckoutUrl($order)
            ?? $this->createCheckoutSession($order, $package, $student, $bookingSource);

        if (! $checkoutUrl) {
            return ['status' => 'error', 'message' => "We couldn't open the payment page just now. Please try the link again in a moment."];
        }

        return ['status' => 'checkout', 'url' => $checkoutUrl];
    }

    /**
     * Signed link to our payment-link page for an order. It stops working when
     * the order's hold runs out.
     */
    public function paymentLinkUrl(Order $order, string $bookingSource): string
    {
        return URL::temporarySignedRoute(
            'payment-link.pay',
            $order->payment_hold_expires_at ?? now()->addDay(),
            ['order' => $order->id, 'source' => $bookingSource],
        );
    }

    /**
     * Reject orders that are not still awaiting their first payment.
     *
     * @throws ValidationException
     */
    protected function ensureOrderAwaitingPayment(Order $order): void
    {
        if ($order->isImported()) {
            throw ValidationException::withMessages([
                'order' => 'Payment links do not apply to imported orders.',
            ]);
        }

        if (! $order->isPending()) {
            $message = match ($order->status) {
                OrderStatus::ACTIVE, OrderStatus::COMPLETED => $order->isWeekly()
                    ? 'This booking is already confirmed. Weekly lesson invoices are sent separately.'
                    : 'This order has already been paid.',
                OrderStatus::CANCELLED => 'This order has been cancelled.',
                default => 'This order is not awaiting payment.',
            };

            throw ValidationException::withMessages(['order' => $message]);
        }

        if ($order->hasPaymentHoldExpired()) {
            throw ValidationException::withMessages([
                'order' => 'The time to pay for this booking has passed, so the lessons are being released. Please book again.',
            ]);
        }
    }

    /**
     * Return the order's existing Stripe Checkout session URL when the session
     * is still open. Returns null when there is no stored session, it has
     * completed or expired, or the lookup fails — the caller then creates a
     * fresh session.
     */
    protected function resolveOpenCheckoutUrl(Order $order): ?string
    {
        if (! $order->stripe_checkout_session_id) {
            return null;
        }

        try {
            $session = Session::retrieve($order->stripe_checkout_session_id);

            return $session->status === 'open' ? ($session->url ?: null) : null;
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve existing checkout session for payment-link resend', [
                'order_id' => $order->id,
                'session_id' => $order->stripe_checkout_session_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify a Stripe Checkout session and activate the order.
     *
     * @return array{verified: bool, order: Order, message: string}
     */
    public function verifyCheckout(Order $order, string $sessionId): array
    {
        if ($order->isWeekly()) {
            return $this->verifyWeeklyCheckout($order, $sessionId);
        }

        $result = ($this->verifyCheckout)($order, $sessionId);

        // Send confirmation email when upfront payment is verified
        if ($result['verified']) {
            $this->sendConfirmationEmail->execute($result['order'], $result['order']->student);

            // Invalidate grouped students cache after payment confirmation
            $this->invalidateStudentCacheForBooking($order->instructor_id);
        }

        return $result;
    }

    /**
     * Verify the Checkout session that took a weekly order's first payment.
     *
     * @return array{verified: bool, order: Order, message: string}
     */
    protected function verifyWeeklyCheckout(Order $order, string $sessionId): array
    {
        if ($order->stripe_checkout_session_id !== $sessionId) {
            return ['verified' => false, 'order' => $order, 'message' => 'Session ID mismatch.'];
        }

        try {
            $session = Session::retrieve($sessionId);
        } catch (\Exception $e) {
            Log::error('Weekly checkout verification failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return ['verified' => false, 'order' => $order, 'message' => 'Failed to verify payment.'];
        }

        if ($session->payment_status !== 'paid') {
            return ['verified' => false, 'order' => $order, 'message' => 'Payment is still processing. Please check back shortly.'];
        }

        $this->confirmWeeklyFirstPayment($order, $session->payment_intent ?: null);

        $order->refresh();

        return $order->isActive()
            ? ['verified' => true, 'order' => $order, 'message' => 'Payment verified. Order is active.']
            : ['verified' => false, 'order' => $order, 'message' => 'This booking could not be confirmed. Please contact us.'];
    }

    /**
     * Record a weekly order's first payment and confirm the booking. Safe to call
     * from both the webhook and the success page — the confirmation email and
     * follow-ups only go out once.
     */
    public function confirmWeeklyFirstPayment(Order $order, ?string $paymentIntentId): bool
    {
        $chargeId = $paymentIntentId
            ? $this->stripeService->getChargeIdForPaymentIntent($paymentIntentId)
            : null;

        if (! ($this->confirmWeeklyFirstPaymentAction)($order, $chargeId)) {
            return false;
        }

        $order->loadMissing(['student', 'instructor']);

        if ($order->student) {
            $this->sendConfirmationEmail->execute($order, $order->student);
        }

        ($this->grantTestPassGuarantee)($order);

        $this->logBookingConfirmed($order);
        $this->invalidateStudentCacheForBooking($order->instructor_id);

        return true;
    }

    /**
     * Release an unpaid order: close its Stripe Checkout session so it can no
     * longer be paid, then free the slots. When Stripe reports the session was
     * already paid (or cannot be reached) the order is kept for the payment
     * webhook to confirm.
     */
    public function releaseUnpaidOrder(Order $order, string $reason): bool
    {
        if (! $order->isPending()) {
            return false;
        }

        if ($order->stripe_checkout_session_id) {
            $expiry = $this->stripeService->expireCheckoutSession($order->stripe_checkout_session_id);

            if (! $expiry['released']) {
                Log::warning('Kept unpaid order: its checkout session could not be closed', [
                    'order_id' => $order->id,
                    'session_id' => $order->stripe_checkout_session_id,
                    'session_status' => $expiry['status'],
                ]);

                return false;
            }
        }

        $released = ($this->releaseUnpaidOrderAction)($order, $reason);

        if ($released) {
            $this->invalidateStudentCacheForBooking($order->instructor_id);
        }

        return $released;
    }

    /**
     * Release every unpaid order whose hold has run out.
     *
     * @return array{released: int, kept: int}
     */
    public function releaseExpiredHolds(): array
    {
        $released = 0;
        $kept = 0;

        Order::query()
            ->where('status', OrderStatus::PENDING)
            ->whereIn('payment_mode', [PaymentMode::UPFRONT, PaymentMode::WEEKLY])
            ->whereNotNull('payment_hold_expires_at')
            ->where('payment_hold_expires_at', '<=', now())
            ->orderBy('payment_hold_expires_at')
            ->each(function (Order $order) use (&$released, &$kept): void {
                try {
                    $this->releaseUnpaidOrder($order, 'payment_window_expired') ? $released++ : $kept++;
                } catch (\Exception $e) {
                    $kept++;

                    Log::error('Failed to release expired order hold', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return ['released' => $released, 'kept' => $kept];
    }

    protected function logBookingConfirmed(Order $order): void
    {
        $metadata = [
            'order_id' => $order->id,
            'package_name' => $order->package_name,
            'lessons_count' => $order->package_lessons_count,
            'payment_mode' => $order->payment_mode->value,
        ];

        try {
            if ($order->student) {
                ($this->logActivity)(
                    $order->student,
                    "Booking confirmed: {$order->package_name} ({$order->package_lessons_count} lessons)",
                    'booking',
                    $metadata
                );
            }

            if ($order->instructor) {
                $studentName = trim(($order->student?->first_name ?? '').' '.($order->student?->surname ?? ''));

                ($this->logActivity)(
                    $order->instructor,
                    "New booking confirmed: {$studentName} — {$order->package_name} ({$order->package_lessons_count} lessons)",
                    'booking',
                    $metadata
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to log booking confirmed activity', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a Stripe invoice (and the accompanying payment-link email) for the next
     * unpaid lesson on a weekly order. No-ops for non-weekly or inactive orders, and
     * is idempotent — the underlying action only creates an invoice when the
     * LessonPayment has no stripe_invoice_id yet.
     *
     * @return array{success: bool, invoice_id?: string, hosted_invoice_url?: string, error?: string}|null
     */
    public function sendNextDueInvoice(Order $order): ?array
    {
        if (! $order->isWeekly() || ! $order->isActive()) {
            return null;
        }

        $lessonPayment = LessonPayment::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_payments.lesson_id')
            ->where('lessons.order_id', $order->id)
            ->where('lessons.status', '!=', LessonStatus::CANCELLED)
            ->whereNotNull('lessons.date')
            ->where('lesson_payments.status', PaymentStatus::DUE)
            ->whereNull('lesson_payments.stripe_invoice_id')
            ->orderBy('lessons.date')
            ->orderBy('lessons.start_time')
            ->select('lesson_payments.*')
            ->with('lesson.order.student.user')
            ->first();

        if (! $lessonPayment) {
            Log::info('No outstanding lesson payment to invoice for order', [
                'order_id' => $order->id,
            ]);

            return null;
        }

        try {
            return ($this->sendLessonInvoice)($lessonPayment);
        } catch (\Exception $e) {
            Log::error('Failed to send next due lesson invoice', [
                'order_id' => $order->id,
                'lesson_payment_id' => $lessonPayment->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Invalidate the instructor's grouped students cache after a booking change.
     */
    protected function invalidateStudentCacheForBooking(?int $instructorId): void
    {
        if (! $instructorId) {
            return;
        }

        $instructor = Instructor::find($instructorId);

        if ($instructor) {
            $this->instructorService->invalidateStudentCache($instructor);
        }
    }
}
