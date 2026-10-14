<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StaffBooking\ListStaffBookedLessonsAction;
use App\Http\Requests\FilterLessonsBookedRequest;
use App\Http\Requests\UpdateManualRefundRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LessonsBookedController extends Controller
{
    public function index(FilterLessonsBookedRequest $request, ListStaffBookedLessonsAction $listStaffBookedLessons): Response
    {
        $paymentState = $request->paymentState();
        $listing = $listStaffBookedLessons($paymentState);

        return Inertia::render('LessonsBooked/Index', [
            'bookings' => $listing['bookings'],
            'counts' => $listing['counts'],
            'filters' => [
                'payment' => $paymentState,
            ],
        ]);
    }

    /**
     * Owner override: tick or untick a booking as refunded outside the
     * platform (e.g. in Stripe or by bank transfer). No Stripe refund is made.
     */
    public function updateRefund(UpdateManualRefundRequest $request, Order $order, OrderService $orderService): JsonResponse
    {
        $refunded = $request->boolean('refunded');
        $order = $orderService->setOrderManualRefund($order, $refunded, $request->user());

        return response()->json([
            'message' => $refunded ? 'Booking marked as refunded.' : 'Refund mark removed from booking.',
            'booking' => [
                'id' => $order->id,
                'manually_refunded_at' => $order->manually_refunded_at?->toIso8601String(),
                'manually_refunded_by_name' => $order->manuallyRefundedBy?->name,
            ],
        ]);
    }
}
