<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInstructorFinanceRequest;
use App\Http\Requests\Api\V1\UpdateInstructorFinanceRequest;
use App\Http\Requests\Api\V1\UploadFinanceReceiptRequest;
use App\Http\Resources\V1\InstructorFinanceResource;
use App\Http\Resources\V1\MileageLogResource;
use App\Models\InstructorFinance;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorFinanceController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Cursor-paginated list of finance records for the authenticated instructor.
     *
     * Query params: `type` (payment|expense), `from` + `to` (Y-m-d), `cursor`, `per_page`.
     * Defaults to the last 30 days when either bound is missing.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:payment,expense'],
            'from' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $instructor = $request->user()->instructor;

        $paginator = $this->instructorService->getFinancesInRange(
            $instructor,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['type'] ?? null,
            (int) ($validated['per_page'] ?? 25)
        );

        return InstructorFinanceResource::collection($paginator);
    }

    /**
     * Overview screen: full-range finances + mileage + stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
        ]);

        $instructor = $request->user()->instructor;

        $summary = $this->instructorService->getFinanceSummary(
            $instructor,
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return response()->json([
            'date_range' => $summary['date_range'],
            'finances' => InstructorFinanceResource::collection($summary['finances'])->resolve(),
            'mileage' => MileageLogResource::collection($summary['mileage'])->resolve(),
            'stats' => $summary['stats'],
        ]);
    }

    /**
     * Dropdown options (categories, payment methods, mileage types, receipt constraints).
     * App should cache this client-side.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'expense_categories' => config('finances.expense_categories', []),
            'payment_categories' => config('finances.payment_categories', []),
            'payment_methods' => config('finances.payment_methods', []),
            'mileage_types' => config('finances.mileage_types', []),
            'receipt' => [
                'max_size_kb' => (int) config('finances.receipt.max_size_kb', 10240),
                'allowed_mimes' => config('finances.receipt.allowed_mimes', []),
            ],
        ]);
    }

    /**
     * Single finance record.
     */
    public function show(Request $request, InstructorFinance $finance): InstructorFinanceResource
    {
        $this->authorizeOwnership($request, $finance);

        return new InstructorFinanceResource($finance);
    }

    /**
     * Create a new finance record for the authenticated instructor.
     */
    public function store(StoreInstructorFinanceRequest $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        $finance = $this->instructorService->createFinance($instructor, $request->validated());

        return (new InstructorFinanceResource($finance))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing finance record.
     */
    public function update(UpdateInstructorFinanceRequest $request, InstructorFinance $finance): InstructorFinanceResource
    {
        $this->authorizeOwnership($request, $finance);

        $finance = $this->instructorService->updateFinance($finance, $request->validated());

        return new InstructorFinanceResource($finance);
    }

    /**
     * Delete a finance record.
     */
    public function destroy(Request $request, InstructorFinance $finance): JsonResponse
    {
        $this->authorizeOwnership($request, $finance);

        $this->instructorService->deleteFinance($finance);

        return response()->json(['message' => 'Finance record deleted successfully.']);
    }

    /**
     * Upload (or replace) the receipt attached to a finance record.
     */
    public function uploadReceipt(UploadFinanceReceiptRequest $request, InstructorFinance $finance): InstructorFinanceResource
    {
        $this->authorizeOwnership($request, $finance);

        $finance = $this->instructorService->uploadFinanceReceipt($finance, $request->file('receipt'));

        return new InstructorFinanceResource($finance);
    }

    /**
     * Remove the receipt attached to a finance record.
     */
    public function destroyReceipt(Request $request, InstructorFinance $finance): InstructorFinanceResource
    {
        $this->authorizeOwnership($request, $finance);

        $finance = $this->instructorService->deleteFinanceReceipt($finance);

        return new InstructorFinanceResource($finance);
    }

    private function authorizeOwnership(Request $request, InstructorFinance $finance): void
    {
        if ($finance->instructor_id !== $request->user()->instructor->id) {
            abort(403, 'You do not own this finance record.');
        }
    }
}
