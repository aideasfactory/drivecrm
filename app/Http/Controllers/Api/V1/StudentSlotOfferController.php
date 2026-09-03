<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptSlotOfferRequest;
use App\Http\Resources\V1\OrderResource;
use App\Http\Resources\V1\SlotOfferResource;
use App\Models\SlotOffer;
use App\Services\SlotOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentSlotOfferController extends Controller
{
    public function __construct(
        protected SlotOfferService $slotOfferService,
    ) {}

    /**
     * List active short-notice lesson offers for the authenticated student.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Student profile not found for the authenticated user.',
            ], 404);
        }

        $offers = $this->slotOfferService->getActiveOffersForStudent($student);

        return SlotOfferResource::collection($offers);
    }

    /**
     * Accept a short-notice offer and book the lesson.
     */
    public function accept(AcceptSlotOfferRequest $request, SlotOffer $slotOffer): JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Student profile not found for the authenticated user.',
            ], 404);
        }

        $paymentMode = PaymentMode::tryFrom((string) $request->input('payment_mode', PaymentMode::UPFRONT->value))
            ?? PaymentMode::UPFRONT;

        $result = $this->slotOfferService->acceptOffer(
            $student,
            $slotOffer,
            $paymentMode,
            returnCheckoutUrl: true,
        );

        $response = [
            'message' => $paymentMode === PaymentMode::WEEKLY
                ? 'Lesson booked. Lesson invoices will be sent before each lesson.'
                : 'Lesson booked. Open the checkout URL to complete payment.',
            'data' => new OrderResource($result['order']),
        ];

        if ($paymentMode === PaymentMode::UPFRONT) {
            $response['checkout_url'] = $result['checkout_url'] ?? null;
        }

        return response()->json($response, 201);
    }
}
