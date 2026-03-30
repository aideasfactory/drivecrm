<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Onboarding\SendOrderConfirmationEmailAction;
use App\Actions\Student\Order\CreateDraftCalendarItemsAction;
use App\Actions\Student\Order\CreateOrderFromApiAction;
use App\Actions\Student\Order\VerifyCheckoutAction;
use App\Enums\PaymentMode;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class OrderService extends BaseService
{
    public function __construct(
        protected CreateDraftCalendarItemsAction $createDraftCalendarItems,
        protected CreateOrderFromApiAction $createOrderFromApi,
        protected VerifyCheckoutAction $verifyCheckout,
        protected SendOrderConfirmationEmailAction $sendOrderConfirmationEmail,
        protected StripeService $stripeService
    ) {}

    /**
     * Book lessons: create calendar items, order, lessons, and handle payment.
     *
     * @return array{order: Order, checkout_url: string|null}
     */
    public function bookLessons(
        Student $student,
        Package $package,
        PaymentMode $paymentMode,
        string $firstLessonDate,
        string $startTime,
        string $endTime
    ): array {
        $calendarItemIds = ($this->createDraftCalendarItems)(
            $student->instructor_id,
            $firstLessonDate,
            $startTime,
            $endTime,
            $package->lessons_count
        );

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
        }

        if ($paymentMode === PaymentMode::WEEKLY) {
            $this->sendOrderConfirmationEmail->execute($order, $student);
        }

        return [
            'order' => $order->fresh(['lessons.lessonPayment']),
            'checkout_url' => $checkoutUrl,
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

        $successUrl = config('app.url').'/api/v1/orders/'.$order->id.'/checkout/verify?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = config('app.url').'/api/v1/orders/'.$order->id.'/checkout/cancelled';

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
     * Get all orders for a student with lessons and payment data.
     *
     * @return Collection<int, Order>
     */
    public function getStudentOrders(Student $student): Collection
    {
        return Order::where('student_id', $student->id)
            ->with(['lessons.lessonPayment'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get a single order with full lesson and payment details.
     */
    public function getOrderDetail(Order $order): Order
    {
        return $order->load(['lessons.lessonPayment', 'package']);
    }

    /**
     * Verify a Stripe Checkout session and activate the order.
     *
     * @return array{verified: bool, order: Order, message: string}
     */
    public function verifyCheckout(Order $order, string $sessionId): array
    {
        return ($this->verifyCheckout)($order, $sessionId);
    }
}
