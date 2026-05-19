<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\VehicleResource;
use App\Services\VehicleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorVehicleController extends Controller
{
    public function __construct(
        protected VehicleService $vehicleService,
    ) {}

    /**
     * List vehicles owned by the authenticated instructor.
     *
     * Defaults to active only. Pass `?include_disposed=1` to include disposed
     * vehicles (for historical context — they cannot be selected on new rows).
     *
     * Mobile v1 is read-only — vehicle CRUD lives on the web. The list is
     * needed so the expense form can show the vehicle picker for method-dependent
     * categories.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'include_disposed' => ['sometimes', 'boolean'],
        ]);

        $instructor = $request->user()->instructor;

        $vehicles = (bool) ($validated['include_disposed'] ?? false)
            ? $this->vehicleService->vehiclesFor($instructor)
            : $this->vehicleService->activeVehiclesFor($instructor);

        return VehicleResource::collection($vehicles);
    }
}
