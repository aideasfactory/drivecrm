<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountCodeRequest;
use App\Models\DiscountCode;
use App\Services\DiscountService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DiscountCodeController extends Controller
{
    public function __construct(
        protected DiscountService $discountService
    ) {}

    /**
     * Display all discount codes.
     */
    public function index(): Response
    {
        $discountCodes = $this->discountService->getAll();

        return Inertia::render('DiscountCodes/Index', [
            'discountCodes' => $discountCodes->map(fn (DiscountCode $code) => [
                'id' => $code->id,
                'label' => $code->label,
                'percentage' => $code->percentage,
                'formatted_percentage' => $code->formatted_percentage,
                'active' => $code->active,
                'orders_count' => $code->orders()->count(),
                'created_at' => $code->created_at?->format('d M Y'),
            ]),
        ]);
    }

    /**
     * Store a new discount code.
     */
    public function store(StoreDiscountCodeRequest $request): JsonResponse
    {
        $discountCode = $this->discountService->create($request->validated());

        return response()->json([
            'discount_code' => [
                'id' => $discountCode->id,
                'label' => $discountCode->label,
                'percentage' => $discountCode->percentage,
                'formatted_percentage' => $discountCode->formatted_percentage,
                'active' => $discountCode->active,
            ],
        ], 201);
    }

    /**
     * Delete a discount code.
     */
    public function destroy(DiscountCode $discountCode): JsonResponse
    {
        $this->discountService->delete($discountCode);

        return response()->json(null, 204);
    }
}
