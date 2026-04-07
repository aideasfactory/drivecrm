<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Book lessons: create order, calendar items, lessons, and handle payment.
     *
     * Upfront payment behaviour depends on who initiates the booking:
     *   - Student (mobile app): the Stripe checkout URL is returned in the response
     *     so the mobile app can load it in an in-app browser.
     *   - Instructor: a payment link is emailed to the student.
     *
     * Weekly payment: order is activated immediately and a confirmation email is sent.
     */
    public function store(CreateOrderRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $validated = $request->validated();

        $package = Package::where('active', true)->findOrFail($validated['package_id']);

        if (! $student->instructor_id) {
            return response()->json([
                'message' => 'Student must have an assigned instructor to book lessons.',
            ], 422);
        }

        $paymentMode = PaymentMode::from($validated['payment_mode']);
        $isStudentInitiated = $request->user()->role === UserRole::STUDENT;

        $result = $this->orderService->bookLessons(
            $student,
            $package,
            $paymentMode,
            $validated['first_lesson_date'],
            $validated['start_time'],
            $validated['end_time'],
            returnCheckoutUrl: $isStudentInitiated
        );

        if ($paymentMode === PaymentMode::WEEKLY) {
            $message = 'Order created and activated. Lesson invoices will be sent before each lesson.';
        } elseif ($isStudentInitiated) {
            $message = 'Order created. Open the checkout URL to complete payment.';
        } else {
            $message = 'Order created. A payment link has been emailed to the student.';
        }

        $response = [
            'message' => $message,
            'data' => new OrderResource($result['order']),
        ];

        if ($isStudentInitiated && $paymentMode === PaymentMode::UPFRONT) {
            $response['checkout_url'] = $result['checkout_url'] ?? null;
        }

        return response()->json($response, 201);
    }

    /**
     * Verify Stripe Checkout payment and activate the order.
     */
    public function verify(Request $request, Order $order): JsonResponse
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return response()->json(['message' => 'Missing session_id parameter.'], 422);
        }

        $student = $order->student;
        Gate::authorize('view', $student);

        $result = $this->orderService->verifyCheckout($order, $sessionId);

        return response()->json([
            'verified' => $result['verified'],
            'message' => $result['message'],
            'data' => new OrderResource($result['order']),
        ], $result['verified'] ? 200 : 422);
    }
}
