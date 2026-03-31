<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInstructorFinanceRequest;
use App\Http\Requests\Api\V1\UpdateInstructorFinanceRequest;
use App\Http\Resources\V1\InstructorFinanceResource;
use App\Models\InstructorFinance;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorFinanceController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * List all finance records for the authenticated instructor.
     */
    public function index(): AnonymousResourceCollection
    {
        $instructor = request()->user()->instructor;

        $finances = $this->instructorService->getFinances($instructor);

        return InstructorFinanceResource::collection($finances);
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
        $instructor = $request->user()->instructor;

        if ($finance->instructor_id !== $instructor->id) {
            abort(403, 'You do not own this finance record.');
        }

        $finance = $this->instructorService->updateFinance($finance, $request->validated());

        return new InstructorFinanceResource($finance);
    }

    /**
     * Delete a finance record.
     */
    public function destroy(InstructorFinance $finance): JsonResponse
    {
        $instructor = request()->user()->instructor;

        if ($finance->instructor_id !== $instructor->id) {
            abort(403, 'You do not own this finance record.');
        }

        $this->instructorService->deleteFinance($finance);

        return response()->json(['message' => 'Finance record deleted successfully.']);
    }
}
