<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Enums\OrderStatus;
use App\Models\Instructor;
use App\Models\Order;
use App\Services\InstructorService;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

class VerifyCheckoutAction
{
    /**
     * Verify a Stripe Checkout session and activate the order if paid.
     *
     * @return array{verified: bool, order: Order, message: string}
     */
    public function __invoke(Order $order, string $sessionId): array
    {
        if ($order->stripe_checkout_session_id !== $sessionId) {
            return [
                'verified' => false,
                'order' => $order,
                'message' => 'Session ID mismatch.',
            ];
        }

        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                if ($order->status === OrderStatus::PENDING) {
                    $order->status = OrderStatus::ACTIVE;
                    $order->stripe_payment_intent_id = $session->payment_intent;
                    $order->save();

                    // Transition calendar items from DRAFT to BOOKED now that payment is confirmed
                    app(ConfirmCalendarItemsAction::class)($order);

                    // Invalidate grouped students cache so the instructor sees the confirmed booking
                    $this->invalidateStudentCache($order->instructor_id);

                    Log::info('Order activated via API checkout verification', [
                        'order_id' => $order->id,
                        'session_id' => $sessionId,
                    ]);
                }

                return [
                    'verified' => true,
                    'order' => $order->fresh(),
                    'message' => 'Payment verified. Order is active.',
                ];
            }

            return [
                'verified' => false,
                'order' => $order,
                'message' => 'Payment is still processing. Please check back shortly.',
            ];
        } catch (\Exception $e) {
            Log::error('Checkout verification failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'verified' => false,
                'order' => $order,
                'message' => 'Failed to verify payment.',
            ];
        }
    }

    protected function invalidateStudentCache(?int $instructorId): void
    {
        if (! $instructorId) {
            return;
        }

        $instructor = Instructor::find($instructorId);

        if ($instructor) {
            app(InstructorService::class)->invalidateStudentCache($instructor);
        }
    }
}
