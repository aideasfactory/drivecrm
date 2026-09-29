<?php

namespace App\Http\Controllers;

use App\Actions\Payment\SendPaymentReceivedEmailsAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Student\GrantTestPassGuaranteeAction;
use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Student;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $verifyResult = app(StripeService::class)->verifyWebhookSignature($payload, $signature);

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

        if ($session->payment_status !== 'paid') {
            Log::info('Webhook: Checkout completed but not yet paid', [
                'order_id' => $order->id,
                'session_id' => $session->id,
            ]);

            return;
        }

        $orderService = app(OrderService::class);

        if ($order->status === OrderStatus::CANCELLED) {
            $orderService->reportPaymentForReleasedOrder($order, $session->id, $session->payment_intent ?? null);

            return;
        }

        // Legacy authenticated-student checkouts created lessons only on payment.
        // Every current booking creates its (draft) lessons when it is held.
        if ($order->isUpfront() && $order->isPending() && $order->lessons()->count() === 0) {
            $this->createLessonsForOrder($order);
        }

        // Confirms once, whichever of the webhook, success page or app verify
        // call arrives first; repeat deliveries do nothing.
        $orderService->confirmPaidCheckoutSession($order, $session->id, $session->payment_intent ?? null);
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

        // The payment_intent.succeeded payload carries the funding charge directly.
        $chargeId = $paymentIntent->latest_charge ?? null;

        if ($order->status === OrderStatus::CANCELLED) {
            app(OrderService::class)->reportPaymentForReleasedOrder($order, $order->stripe_checkout_session_id, $paymentIntent->id);

            return;
        }

        if ($order->isUpfront()) {
            // Confirms a still-pending order once, or backfills the charge id.
            app(OrderService::class)->confirmUpfrontPayment($order, $paymentIntent->id, $chargeId, $order->stripe_checkout_session_id);

            return;
        }

        if ($chargeId && ! $order->stripe_charge_id) {
            $order->update(['stripe_charge_id' => $chargeId]);
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
        $instructor = Instructor::where('stripe_account_id', $account->id)->first();

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

        Log::info('Webhook [invoice.paid]: START', [
            'invoice_id' => $invoice->id,
            'amount_paid' => $invoice->amount_paid ?? null,
            'customer' => $invoice->customer ?? null,
            'metadata' => isset($invoice->metadata) ? (array) $invoice->metadata : [],
        ]);

        // Try lesson_payment_id first (new invoices), fall back to lesson_id lookup
        $lessonPaymentId = $invoice->metadata->lesson_payment_id ?? null;
        $lessonId = $invoice->metadata->lesson_id ?? null;

        Log::info('Webhook [invoice.paid]: Extracted metadata', [
            'lesson_payment_id' => $lessonPaymentId,
            'lesson_id' => $lessonId,
        ]);

        if (! $lessonPaymentId && ! $lessonId) {
            Log::warning('Webhook [invoice.paid]: No lesson identifiers in metadata — skipping', [
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        // Find the lesson payment
        $lessonPayment = $lessonPaymentId
            ? LessonPayment::find($lessonPaymentId)
            : LessonPayment::where('lesson_id', $lessonId)->first();

        if (! $lessonPayment) {
            Log::error('Webhook [invoice.paid]: Lesson payment NOT FOUND in database', [
                'lesson_payment_id' => $lessonPaymentId,
                'lesson_id' => $lessonId,
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        Log::info('Webhook [invoice.paid]: Found lesson payment', [
            'lesson_payment_id' => $lessonPayment->id,
            'current_status' => $lessonPayment->status->value,
            'amount_pence' => $lessonPayment->amount_pence,
        ]);

        // Resolve the funding charge id for this invoice so the lesson payout can later
        // cite it as the transfer's source_transaction. The invoice payload usually
        // carries `charge` directly; fall back to the payment intent's latest charge.
        $chargeId = $invoice->charge
            ?? app(StripeService::class)->getChargeIdForInvoice($invoice->id);

        // Mark lesson payment as paid
        $lessonPayment->update([
            'status' => PaymentStatus::PAID,
            'stripe_invoice_id' => $invoice->id,
            'stripe_charge_id' => $chargeId,
            'paid_at' => now(),
        ]);

        Log::info('Webhook [invoice.paid]: Lesson payment updated to PAID', [
            'lesson_payment_id' => $lessonPayment->id,
        ]);

        // Load relationships for notifications
        $lesson = $lessonPayment->lesson;
        $order = $lesson?->order;
        $student = $order?->student;
        $instructor = $order?->instructor;

        Log::info('Webhook [invoice.paid]: Loaded relationships', [
            'lesson_id' => $lesson?->id,
            'lesson_date' => $lesson?->date?->format('Y-m-d'),
            'order_id' => $order?->id,
            'student_id' => $student?->id,
            'instructor_id' => $instructor?->id,
        ]);

        // Update the calendar item status to BOOKED if still in DRAFT/RESERVED
        if ($lesson && $lesson->calendarItem) {
            $calendarItem = $lesson->calendarItem;
            $previousStatus = $calendarItem->status?->value;

            if (in_array($calendarItem->status, [CalendarItemStatus::DRAFT, CalendarItemStatus::RESERVED])) {
                $calendarItem->update(['status' => CalendarItemStatus::BOOKED]);

                Log::info('Webhook [invoice.paid]: Calendar item updated to BOOKED', [
                    'calendar_item_id' => $calendarItem->id,
                    'previous_status' => $previousStatus,
                ]);
            } else {
                Log::info('Webhook [invoice.paid]: Calendar item already in correct status', [
                    'calendar_item_id' => $calendarItem->id,
                    'status' => $previousStatus,
                ]);
            }
        }

        if ($order && $lessonPayment->test_pass_guarantee_pence > 0) {
            app(GrantTestPassGuaranteeAction::class)($order);
        }

        // Log activity for the student
        if ($student) {
            $lessonDate = $lesson->date?->format('d M Y') ?? 'N/A';

            try {
                app(LogActivityAction::class)(
                    $student,
                    "Payment received for lesson on {$lessonDate} ({$lessonPayment->formatted_amount})",
                    'payment',
                    [
                        'type' => 'lesson_payment_received',
                        'lesson_payment_id' => $lessonPayment->id,
                        'lesson_id' => $lesson->id,
                        'invoice_id' => $invoice->id,
                    ]
                );

                Log::info('Webhook [invoice.paid]: Activity logged for student');
            } catch (\Exception $e) {
                Log::error('Webhook [invoice.paid]: Failed to log activity', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send payment confirmation email to student/contact
        app(SendPaymentReceivedEmailsAction::class)($lessonPayment, $student, $instructor);

        Log::info('Webhook [invoice.paid]: COMPLETE', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonPayment->lesson_id,
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
}
