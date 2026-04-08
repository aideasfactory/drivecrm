<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Calendar\DetectCalendarClashesAction;
use App\Actions\Onboarding\SendOrderConfirmationEmailAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Student\Order\CreateDraftCalendarItemsAction;
use App\Actions\Student\Order\CreateOrderFromApiAction;
use App\Actions\Student\Order\SendPaymentLinkEmailAction;
use App\Actions\Student\Order\VerifyCheckoutAction;
use App\Enums\PaymentMode;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Notifications\CalendarClashDetectedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        protected InstructorService $instructorService
    ) {}

    /**
     * Book lessons: create calendar items, order, lessons, and handle payment.
     *
     * When $returnCheckoutUrl is true (student-initiated mobile bookings), the Stripe
     * checkout URL is returned in the response instead of being emailed to the student.
     * The mobile app is expected to load this URL in an in-app browser. When false
     * (instructor-initiated bookings), the URL is emailed to the student.
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
        bool $returnCheckoutUrl = false
    ): array {
        $calendarItemIds = ($this->createDraftCalendarItems)(
            $student->instructor_id,
            $firstLessonDate,
            $startTime,
            $endTime,
            $package->lessons_count
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

        $checkoutUrl = null;

        if ($paymentMode === PaymentMode::UPFRONT) {
            $checkoutUrl = $this->createCheckoutSession($order, $package, $student);

            if ($checkoutUrl && ! $returnCheckoutUrl) {
                $this->sendPaymentLinkEmail->execute($order, $student, $checkoutUrl);
            }
        }

        // Send confirmation email for weekly orders (activated immediately)
        if ($paymentMode === PaymentMode::WEEKLY) {
            $this->ensureStripeCustomerExists($student);
            $this->sendConfirmationEmail->execute($order, $student);
        }

        // Invalidate grouped students cache so the instructor sees the new booking immediately
        $this->invalidateStudentCacheForBooking($student->instructor_id);

        return [
            'order' => $order->fresh(['lessons']),
            'checkout_url' => $returnCheckoutUrl ? $checkoutUrl : null,
        ];
    }

    /**
     * Create a Stripe Checkout session for upfront payment.
     */
    protected function createCheckoutSession(Order $order, Package $package, Student $student): ?string
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
            $cancelUrl
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
     * Verify a Stripe Checkout session and activate the order.
     *
     * @return array{verified: bool, order: Order, message: string}
     */
    public function verifyCheckout(Order $order, string $sessionId): array
    {
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
     * Ensure the student's user has a Stripe customer ID (required for weekly invoice sending).
     */
    protected function ensureStripeCustomerExists(Student $student): void
    {
        $user = $student->user;

        if ($user->stripe_customer_id) {
            return;
        }

        $customerResult = $this->stripeService->createOrGetCustomer($user);

        if (! $customerResult['success']) {
            Log::warning('Failed to create Stripe customer for weekly order', [
                'user_id' => $user->id,
                'student_id' => $student->id,
                'error' => $customerResult['error'] ?? 'Unknown',
            ]);

            return;
        }

        $user->stripe_customer_id = $customerResult['customer_id'];
        $user->save();
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
