<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\BookingPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles payment links emailed to the student (via SendPaymentLinkEmailAction)
 * and the post-payment return from Stripe.
 *
 * These routes are intentionally unauthenticated: the student is clicking
 * through from their email client and has no app session. The emailed link is
 * a signed URL; the Stripe return pages match the Stripe session ID against the
 * order's stored stripe_checkout_session_id — the same capability-by-session-id
 * trust model used by the onboarding checkout return (StepSixController).
 */
class PaymentLinkCheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Emailed payment link. Opens Stripe Checkout while the booking's hold is
     * live, otherwise explains why it can't be paid. The signature is checked
     * without its expiry so a late click gets a friendly page, not a 403.
     */
    public function pay(Request $request, Order $order): RedirectResponse|Response
    {
        abort_unless(URL::hasCorrectSignature($request), 403);

        $source = $request->query('source');
        $result = $this->orderService->openPaymentLinkCheckout($order, is_string($source) ? $source : 'payment_link');

        if ($result['status'] === 'checkout') {
            return redirect()->away($result['url']);
        }

        Log::info('Payment link opened but the booking cannot be paid', [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'result' => $result['status'],
        ]);

        return Inertia::render('PaymentLink/Unavailable', [
            'reason' => $result['status'],
            'message' => $result['message'],
            'order' => $this->formatOrder($order->loadMissing(['package', 'instructor.user'])),
        ]);
    }

    /**
     * Stripe success_url target. Verifies the checkout session and renders
     * the confirmation page. Does NOT require auth — the incoming session_id
     * is the capability.
     */
    public function success(Request $request, Order $order): Response
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId || ! is_string($sessionId)) {
            Log::warning('Payment link success hit without session_id', [
                'order_id' => $order->id,
            ]);

            return Inertia::render('PaymentLink/Success', [
                'verified' => false,
                'message' => 'Missing checkout session reference.',
                'order' => $this->formatOrder($order->fresh(['package', 'instructor.user', 'student'])),
            ]);
        }

        $result = $this->orderService->verifyCheckout($order, $sessionId);

        return Inertia::render('PaymentLink/Success', [
            'verified' => $result['verified'],
            'message' => $result['message'],
            'weeklyPaymentDueHours' => BookingPayments::weeklyPaymentDueHoursBeforeLesson(),
            'order' => $this->formatOrder(
                $result['order']->loadMissing(['package', 'instructor.user', 'student'])
            ),
        ]);
    }

    /**
     * Stripe cancel_url target. Renders a friendly cancel page so the student
     * knows the payment wasn't taken and how long they have to try again.
     */
    public function cancel(Request $request, Order $order): Response
    {
        Log::info('Payment link checkout cancelled by student', [
            'order_id' => $order->id,
            'order_status' => $order->status,
        ]);

        return Inertia::render('PaymentLink/Cancelled', [
            'order' => $this->formatOrder($order->loadMissing(['package', 'instructor.user'])),
        ]);
    }

    /**
     * Produce a minimal, display-safe summary for the Inertia page. We
     * intentionally do NOT expose the full order resource here because these
     * routes are unauthenticated.
     *
     * @return array<string, mixed>
     */
    protected function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status instanceof OrderStatus
                ? $order->status->value
                : (string) $order->status,
            'payment_mode' => $order->payment_mode?->value,
            'total_price_pence' => $order->total_price_pence ?? $order->package_total_price_pence,
            'amount_paid_pence' => $this->amountPaidPence($order),
            'pay_by' => $order->isAwaitingFirstPayment() && $order->payment_hold_expires_at
                ? BookingPayments::formatDeadline($order->payment_hold_expires_at)
                : null,
            'package_total_price_pence' => $order->package_total_price_pence,
            'booking_fee_pence' => $order->booking_fee_pence,
            'digital_fee_pence' => $order->digital_fee_pence,
            'test_pass_guarantee_pence' => $order->includes_test_pass_guarantee ? (int) $order->test_pass_guarantee_pence : null,
            // What the first payment covered: the full amount for pay in full, week 1 for weekly
            'first_payment' => $order->firstPaymentBreakdown(),
            'weekly_instalment' => $order->isWeekly() ? $order->formatted_weekly_instalment : null,
            'package' => $order->package ? [
                'name' => $order->package->name,
                'lessons_count' => $order->package->lessons_count,
            ] : null,
            'instructor' => $order->instructor && $order->instructor->user ? [
                'name' => $order->instructor->user->name,
            ] : null,
        ];
    }

    protected function amountPaidPence(Order $order): ?int
    {
        if (! $order->isActive() && $order->status !== OrderStatus::COMPLETED) {
            return null;
        }

        if ($order->isWeekly()) {
            $firstPayment = $order->firstLessonPayment();

            return $firstPayment?->isPaid() ? (int) $firstPayment->amount_pence : null;
        }

        return $order->total_price_pence ?? $order->package_total_price_pence;
    }
}
