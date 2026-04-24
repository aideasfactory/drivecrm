<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMileageLogRequest;
use App\Http\Requests\Api\V1\UpdateMileageLogRequest;
use App\Http\Resources\V1\MileageLogResource;
use App\Models\MileageLog;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorMileageController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Cursor-paginated list of mileage logs for the authenticated instructor.
     *
     * Query params: `from` + `to` (Y-m-d), `cursor`, `per_page`.
     * Defaults to the last 30 days when either bound is missing.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $instructor = $request->user()->instructor;

        $paginator = $this->instructorService->getMileageLogsInRange(
            $instructor,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['per_page'] ?? 25)
        );

        return MileageLogResource::collection($paginator);
    }

    /**
     * Single mileage log.
     */
    public function show(Request $request, MileageLog $mileageLog): MileageLogResource
    {
        $this->authorizeOwnership($request, $mileageLog);

        return new MileageLogResource($mileageLog);
    }

    /**
     * Create a mileage log for the authenticated instructor.
     */
    public function store(StoreMileageLogRequest $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        $log = $this->instructorService->createMileageLog($instructor, $request->validated());

        return (new MileageLogResource($log))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a mileage log.
     */
    public function update(UpdateMileageLogRequest $request, MileageLog $mileageLog): MileageLogResource
    {
        $this->authorizeOwnership($request, $mileageLog);

        $log = $this->instructorService->updateMileageLog($mileageLog, $request->validated());

        return new MileageLogResource($log);
    }

    /**
     * Delete a mileage log.
     */
    public function destroy(Request $request, MileageLog $mileageLog): JsonResponse
    {
        $this->authorizeOwnership($request, $mileageLog);

        $this->instructorService->deleteMileageLog($mileageLog);

        return response()->json(['message' => 'Mileage log deleted successfully.']);
    }

    private function authorizeOwnership(Request $request, MileageLog $log): void
    {
        if ($log->instructor_id !== $request->user()->instructor->id) {
            abort(403, 'You do not own this mileage log.');
        }
    }
}
