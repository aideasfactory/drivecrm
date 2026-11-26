<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SetDiscountCodeActiveRequest;
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
        return Inertia::render('DiscountCodes/Index', [
            'discountCodes' => $this->discountService->getAll()
                ->map(fn (DiscountCode $code): array => $this->present($code))
                ->values(),
        ]);
    }

    /**
     * Store a new discount code.
     */
    public function store(StoreDiscountCodeRequest $request): JsonResponse
    {
        $discountCode = $this->discountService->create($request->validated());

        return response()->json(['discount_code' => $this->present($discountCode)], 201);
    }

    /**
     * Switch a code on or off. Codes are never deleted.
     */
    public function setActive(SetDiscountCodeActiveRequest $request, DiscountCode $discountCode): JsonResponse
    {
        $discountCode = $this->discountService->setActive($discountCode, $request->boolean('active'));

        return response()->json(['discount_code' => $this->present($discountCode)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(DiscountCode $code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'label' => $code->label,
            'type' => $code->type?->value,
            'percentage' => $code->percentage,
            'amount_pence' => $code->amount_pence,
            'formatted_percentage' => $code->formatted_percentage,
            'active' => $code->active,
            'orders_count' => (int) ($code->used_orders_count ?? 0),
            'created_at' => $code->created_at?->format('d M Y'),
        ];
    }
}
