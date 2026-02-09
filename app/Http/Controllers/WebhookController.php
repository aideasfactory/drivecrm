<?php

namespace App\Http\Controllers;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            Log::error('Webhook: Missing Stripe signature');

            return response()->json(['error' => 'Missing signature'], 400);
        }

        try {
            // Verify webhook signature
            $verifyResult = app(\App\Services\StripeService::class)->verifyWebhookSignature($payload, $signature);

            if (! $verifyResult['success']) {
                Log::error('Webhook: Signature verification failed', [
                    'error' => $verifyResult['error'],
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $event = $verifyResult['event'];

            // Check for idempotency
            if (WebhookEvent::hasBeenProcessed($event->id)) {
                Log::info('Webhook: Event already processed', [
                    'event_id' => $event->id,
                    'type' => $event->type,
                ]);

                return response()->json(['status' => 'already_processed'], 200);
            }

            // Record the webhook event
            WebhookEvent::create([
                'stripe_event_id' => $event->id,
                'type' => $event->type,
                'payload' => json_decode($payload, true),
            ]);

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event);
                    break;

                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event);
                    break;

                case 'account.updated':
                    $this->handleAccountUpdated($event);
                    break;

                case 'invoice.paid':
                    $this->handleInvoicePaid($event);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event);
                    break;

                default:
                    Log::info('Webhook: Unhandled event type', [
                        'event_id' => $event->id,
                        'type' => $event->type,
                    ]);
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Webhook: Processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle checkout.session.completed event.
     */
    protected function handleCheckoutSessionCompleted(object $event): void
    {
        $session = $event->data->object;

        Log::info('Webhook: Processing checkout.session.completed', [
            'session_id' => $session->id,
            'mode' => $session->mode,
            'payment_status' => $session->payment_status ?? null,
        ]);

        // Find the order by checkout session ID
        $order = Order::where('stripe_checkout_session_id', $session->id)->first();

        if (! $order) {
            Log::warning('Webhook: Order not found for checkout session', [
                'session_id' => $session->id,
            ]);

            return;
        }

        if ($order->isUpfront()) {
            // UPFRONT PAYMENT MODE: Process payment completion
            if ($session->payment_status === 'paid') {
                try {
                    DB::beginTransaction();

                    // Update order status and payment intent ID
                    $order->stripe_payment_intent_id = $session->payment_intent;
                    $order->status = OrderStatus::ACTIVE;
                    $order->save();

                    // For authenticated student checkout, create lessons
                    // For onboarding, lessons already created in StepSixController
                    if ($order->lessons()->count() === 0) {
                        $this->createLessonsForOrder($order);
                    }

                    // Send confirmation email (for onboarding orders)
                    $this->sendOrderConfirmationEmail($order);

                    // Mark calendar item as unavailable (for onboarding)
                    $this->markCalendarItemUnavailable($order);

                    DB::commit();

                    Log::info('Webhook: Upfront order activated', [
                        'order_id' => $order->id,
                        'lessons_count' => $order->package->lessons_count,
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Webhook: Failed to activate upfront order', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }
        // Weekly payment mode is now handled via invoice.paid webhook
        // No setup session needed
    }

    /**
     * Handle payment_intent.succeeded event.
     */
    protected function handlePaymentIntentSucceeded(object $event): void
    {
        $paymentIntent = $event->data->object;

        Log::info('Webhook: Processing payment_intent.succeeded', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        // Find order by payment intent ID
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $order) {
            Log::info('Webhook: No order found for payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return;
        }

        // Update order status if not already active
        if ($order->status === OrderStatus::PENDING) {
            try {
                DB::beginTransaction();

                $order->status = OrderStatus::ACTIVE;
                $order->save();

                // Create lessons if not already created
                if ($order->lessons()->count() === 0) {
                    $this->createLessonsForOrder($order);
                }

                DB::commit();

                Log::info('Webhook: Order activated via payment intent', [
                    'order_id' => $order->id,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Webhook: Failed to activate order via payment intent', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle payment_intent.payment_failed event.
     */
    protected function handlePaymentIntentFailed(object $event): void
    {
        $paymentIntent = $event->data->object;

        Log::warning('Webhook: Payment failed', [
            'payment_intent_id' => $paymentIntent->id,
            'last_payment_error' => $paymentIntent->last_payment_error ?? null,
        ]);

        // Could update order status or send notification to student
    }

    /**
     * Handle account.updated event (for instructor Stripe Connect accounts).
     */
    protected function handleAccountUpdated(object $event): void
    {
        $account = $event->data->object;

        Log::info('Webhook: Processing account.updated', [
            'account_id' => $account->id,
            'charges_enabled' => $account->charges_enabled ?? false,
            'payouts_enabled' => $account->payouts_enabled ?? false,
        ]);

        // Find instructor by Stripe account ID
        $instructor = \App\Models\Instructor::where('stripe_account_id', $account->id)->first();

        if (! $instructor) {
            Log::info('Webhook: No instructor found for account', [
                'account_id' => $account->id,
            ]);

            return;
        }

        // Update instructor status
        $instructor->onboarding_complete = ($account->details_submitted ?? false);
        $instructor->charges_enabled = ($account->charges_enabled ?? false);
        $instructor->payouts_enabled = ($account->payouts_enabled ?? false);
        $instructor->save();

        Log::info('Webhook: Instructor account updated', [
            'instructor_id' => $instructor->id,
            'onboarding_complete' => $instructor->onboarding_complete,
        ]);
    }

    /**
     * Handle invoice.paid event (for weekly payments).
     */
    protected function handleInvoicePaid(object $event): void
    {
        $invoice = $event->data->object;

        Log::info('Webhook: Processing invoice.paid', [
            'invoice_id' => $invoice->id,
        ]);

        // Get lesson_id from invoice metadata
        $lessonId = $invoice->metadata->lesson_id ?? null;

        if (! $lessonId) {
            Log::warning('Webhook: Invoice has no lesson_id in metadata', [
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        // Find the lesson payment
        $lessonPayment = LessonPayment::whereHas('lesson', function ($query) use ($lessonId) {
            $query->where('id', $lessonId);
        })->first();

        if (! $lessonPayment) {
            Log::warning('Webhook: Lesson payment not found', [
                'lesson_id' => $lessonId,
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        // Mark as paid
        $lessonPayment->update([
            'status' => PaymentStatus::PAID,
            'stripe_invoice_id' => $invoice->id,
            'paid_at' => now(),
        ]);

        Log::info('Webhook: Lesson payment marked as paid', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonId,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Handle invoice.payment_failed event (for weekly payments).
     */
    protected function handleInvoicePaymentFailed(object $event): void
    {
        $invoice = $event->data->object;
        $lessonId = $invoice->metadata->lesson_id ?? null;

        Log::error('Webhook: Invoice payment failed', [
            'invoice_id' => $invoice->id,
            'lesson_id' => $lessonId,
            'amount' => $invoice->amount_due,
        ]);

        // Stripe will handle retries automatically
        // Could send notification to student here (future enhancement)
    }

    /**
     * Create lessons for an order.
     */
    protected function createLessonsForOrder(Order $order): void
    {
        $package = $order->package;

        // Check if lessons already exist
        if ($order->lessons()->count() > 0) {
            Log::info('Lessons already exist for order', [
                'order_id' => $order->id,
            ]);

            return;
        }

        // Create lessons
        for ($i = 0; $i < $package->lessons_count; $i++) {
            Lesson::create([
                'order_id' => $order->id,
                'instructor_id' => $package->instructor_id,
                'amount_pence' => $package->lesson_price_pence,
                'status' => LessonStatus::PENDING,
            ]);
        }

        Log::info('Created lessons for order', [
            'order_id' => $order->id,
            'lessons_count' => $package->lessons_count,
        ]);
    }

    /**
     * Send order confirmation email (for onboarding orders).
     */
    protected function sendOrderConfirmationEmail(Order $order): void
    {
        try {
            $sendEmailAction = app(\App\Actions\Onboarding\SendOrderConfirmationEmailAction::class);
            $sendEmailAction->execute($order, $order->student);

            Log::info('Webhook: Order confirmation email queued', [
                'order_id' => $order->id,
                'student_id' => $order->student_id,
            ]);
        } catch (\Exception $e) {
            // Log but don't throw - email failure shouldn't break webhook
            Log::error('Webhook: Failed to send order confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark calendar item as unavailable (for onboarding orders).
     */
    protected function markCalendarItemUnavailable(Order $order): void
    {
        try {
            // Get first lesson which might have calendar_item_id
            $firstLesson = $order->lessons()->orderBy('date')->first();

            if ($firstLesson && $firstLesson->calendar_item_id) {
                $calendarItem = \App\Models\CalendarItem::find($firstLesson->calendar_item_id);

                if ($calendarItem) {
                    $calendarItem->is_available = false;
                    $calendarItem->save();

                    Log::info('Webhook: Marked calendar item as unavailable', [
                        'order_id' => $order->id,
                        'calendar_item_id' => $calendarItem->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't throw - calendar update failure shouldn't break webhook
            Log::error('Webhook: Failed to mark calendar item unavailable', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
