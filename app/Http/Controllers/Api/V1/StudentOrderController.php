<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StudentOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * List all orders for a student with lessons and payment data.
     */
    public function index(Student $student): AnonymousResourceCollection
    {
        Gate::authorize('view', $student);

        $orders = $this->orderService->getStudentOrders($student);

        return OrderResource::collection($orders);
    }

    /**
     * Show a single order with full lesson and payment details.
     */
    public function show(Student $student, Order $order): OrderResource
    {
        Gate::authorize('view', $student);

        if ($order->student_id !== $student->id) {
            abort(404);
        }

        $order = $this->orderService->getOrderDetail($order);

        return new OrderResource($order);
    }

    /**
     * Book lessons: create order, calendar items, lessons, and initiate payment.
     *
     * For upfront payment: returns a checkout_url for Stripe Checkout.
     * For weekly payment: order is activated immediately.
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

        $result = $this->orderService->bookLessons(
            $student,
            $package,
            $paymentMode,
            $validated['first_lesson_date'],
            $validated['start_time'],
            $validated['end_time']
        );

        $response = [
            'message' => $paymentMode === PaymentMode::WEEKLY
                ? 'Order created and activated. Lesson invoices will be sent before each lesson.'
                : 'Order created. Complete payment to activate.',
            'data' => new OrderResource($result['order']),
        ];

        if ($result['checkout_url']) {
            $response['checkout_url'] = $result['checkout_url'];
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
