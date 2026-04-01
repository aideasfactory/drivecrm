<?php

namespace App\Http\Controllers;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Actions\Shared\LogActivityAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Notifications\InstructorLessonPaymentReceivedNotification;
use App\Notifications\LessonPaymentReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

                    // Create lesson payment records for upfront orders (marked as PAID immediately)
                    $this->createUpfrontLessonPayments($order);

                    // Transition calendar items from DRAFT to BOOKED now that payment is confirmed
                    app(ConfirmCalendarItemsAction::class)($order);

                    DB::commit();

                    // Log activity for booking confirmation
                    $this->logBookingConfirmedActivity($order);

                    Log::info('Webhook: Upfront order activated', [
                        'order_id' => $order->id,
                        'lessons_count' => $order->package_lessons_count,
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

                // Transition calendar items from DRAFT to BOOKED now that payment is confirmed
                app(ConfirmCalendarItemsAction::class)($order);

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

        // Mark lesson payment as paid
        $lessonPayment->update([
            'status' => PaymentStatus::PAID,
            'stripe_invoice_id' => $invoice->id,
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

            if (in_array($calendarItem->status, [\App\Enums\CalendarItemStatus::DRAFT, \App\Enums\CalendarItemStatus::RESERVED])) {
                $calendarItem->update(['status' => \App\Enums\CalendarItemStatus::BOOKED]);

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
        $this->sendPaymentReceivedEmails($lessonPayment, $student, $instructor);

        Log::info('Webhook [invoice.paid]: COMPLETE', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonPayment->lesson_id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Send payment received confirmation emails to student and instructor.
     */
    protected function sendPaymentReceivedEmails(LessonPayment $lessonPayment, ?\App\Models\Student $student, ?\App\Models\Instructor $instructor): void
    {
        if (! $student) {
            Log::warning('Webhook [invoice.paid]: No student found — skipping emails');

            return;
        }

        $isBookedByContact = ! $student->owns_account;

        // Email to student or contact
        try {
            $recipientEmail = $isBookedByContact
                ? $student->contact_email
                : $student->email;

            if ($recipientEmail) {
                Notification::route('mail', $recipientEmail)
                    ->notify(new LessonPaymentReceivedNotification($lessonPayment, $student, $isBookedByContact));

                Log::info('Webhook [invoice.paid]: Payment confirmation email queued for student', [
                    'recipient_email' => $recipientEmail,
                    'lesson_payment_id' => $lessonPayment->id,
                ]);
            } else {
                Log::warning('Webhook [invoice.paid]: No student email — skipping student notification');
            }
        } catch (\Exception $e) {
            Log::error('Webhook [invoice.paid]: Failed to send student payment confirmation', [
                'error' => $e->getMessage(),
            ]);
        }

        // Email to instructor
        try {
            $instructorEmail = $instructor?->user?->email;

            if ($instructorEmail) {
                Notification::route('mail', $instructorEmail)
                    ->notify(new InstructorLessonPaymentReceivedNotification($lessonPayment, $student));

                Log::info('Webhook [invoice.paid]: Payment notification email queued for instructor', [
                    'instructor_email' => $instructorEmail,
                    'lesson_payment_id' => $lessonPayment->id,
                ]);
            } else {
                Log::warning('Webhook [invoice.paid]: No instructor email — skipping instructor notification');
            }
        } catch (\Exception $e) {
            Log::error('Webhook [invoice.paid]: Failed to send instructor payment notification', [
                'error' => $e->getMessage(),
            ]);
        }
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
     * Create lesson payment records for upfront orders (marked as PAID immediately).
     */
    protected function createUpfrontLessonPayments(Order $order): void
    {
        $lessons = $order->lessons()->orderBy('date')->get();

        foreach ($lessons as $lesson) {
            // Skip if a payment record already exists for this lesson
            if (LessonPayment::where('lesson_id', $lesson->id)->exists()) {
                continue;
            }

            LessonPayment::create([
                'lesson_id' => $lesson->id,
                'amount_pence' => $lesson->amount_pence,
                'status' => PaymentStatus::PAID,
                'due_date' => $lesson->date,
                'paid_at' => now(),
            ]);
        }

        Log::info('Webhook: Created upfront lesson payment records', [
            'order_id' => $order->id,
            'payments_count' => $lessons->count(),
        ]);
    }

    /**
     * Log activity for both student and instructor when a booking is confirmed.
     */
    protected function logBookingConfirmedActivity(Order $order): void
    {
        try {
            $logActivity = app(LogActivityAction::class);
            $metadata = [
                'order_id' => $order->id,
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'payment_mode' => $order->payment_mode->value,
            ];

            // Log for student
            if ($order->student) {
                $logActivity(
                    $order->student,
                    "Booking confirmed: {$order->package_name} ({$order->package_lessons_count} lessons)",
                    'booking',
                    $metadata
                );
            }

            // Log for instructor
            if ($order->instructor) {
                $studentName = trim(($order->student->first_name ?? '').' '.($order->student->surname ?? ''));
                $logActivity(
                    $order->instructor,
                    "New booking confirmed: {$studentName} — {$order->package_name} ({$order->package_lessons_count} lessons)",
                    'booking',
                    $metadata
                );
            }
        } catch (\Exception $e) {
            // Log but don't throw - activity logging failure shouldn't break webhook
            Log::error('Webhook: Failed to log booking confirmed activity', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
