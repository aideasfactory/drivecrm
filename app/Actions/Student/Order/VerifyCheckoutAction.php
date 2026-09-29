<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

class VerifyCheckoutAction
{
    /**
     * Check a Stripe Checkout session against its order. Does not confirm the
     * order — the caller does that through OrderService so the confirmation
     * and its emails happen exactly once.
     *
     * `paid` is true when the session belongs to the order and has been paid.
     * `paid_after_release` is true when it was paid but the booking had already
     * been released.
     *
     * @return array{paid: bool, paid_after_release: bool, payment_intent: string|null, message: string}
     */
    public function __invoke(Order $order, string $sessionId): array
    {
        $result = [
            'paid' => false,
            'paid_after_release' => false,
            'payment_intent' => null,
            'message' => 'Payment is still processing. Please check back shortly.',
        ];

        if ($order->stripe_checkout_session_id !== $sessionId) {
            return ['message' => 'Session ID mismatch.'] + $result;
        }

        try {
            $session = Session::retrieve($sessionId);
        } catch (\Exception $e) {
            Log::error('Checkout verification failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return ['message' => 'Failed to verify payment.'] + $result;
        }

        $isPaid = $session->payment_status === 'paid';
        $paymentIntent = $session->payment_intent ?: null;

        if ($order->status === OrderStatus::CANCELLED) {
            return [
                'paid' => false,
                'paid_after_release' => $isPaid,
                'payment_intent' => $paymentIntent,
                'message' => 'The time to pay for this booking ran out and the lessons were released. Any payment taken will be refunded.',
            ];
        }

        return [
            'paid' => $isPaid,
            'paid_after_release' => false,
            'payment_intent' => $paymentIntent,
            'message' => $isPaid ? 'Payment verified.' : $result['message'],
        ];
    }
}
