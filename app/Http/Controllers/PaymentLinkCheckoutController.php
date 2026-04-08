<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the post-payment return from Stripe for payment links that the
 * instructor emails to the student (via SendPaymentLinkEmailAction).
 *
 * These routes are intentionally unauthenticated: the student is clicking
 * through from their email client and has no app session. Security comes
 * from matching the Stripe session ID against the order's stored
 * stripe_checkout_session_id — the same capability-by-session-id trust
 * model used by the onboarding checkout return (StepSixController).
 */
class PaymentLinkCheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

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
            'order' => $this->formatOrder(
                $result['order']->loadMissing(['package', 'instructor.user', 'student'])
            ),
        ]);
    }

    /**
     * Stripe cancel_url target. Renders a friendly cancel page so the student
     * knows the payment wasn't taken and that they can ask the instructor to
     * resend the link.
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
            'total_price_pence' => $order->total_price_pence ?? $order->package_total_price_pence,
            'package' => $order->package ? [
                'name' => $order->package->name,
                'lessons_count' => $order->package->lessons_count,
            ] : null,
            'instructor' => $order->instructor && $order->instructor->user ? [
                'name' => $order->instructor->user->name,
            ] : null,
        ];
    }
}
