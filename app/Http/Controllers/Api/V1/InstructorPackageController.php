<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInstructorPackageRequest;
use App\Http\Requests\Api\V1\UpdateInstructorPackageRequest;
use App\Http\Resources\V1\PackageResource;
use App\Models\Package;
use App\Services\InstructorPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorPackageController extends Controller
{
    public function __construct(
        protected InstructorPackageService $packageService
    ) {}

    /**
     * Return active packages for the authenticated instructor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $instructor = $request->user()->instructor;

        $packages = $this->packageService->getPackages($instructor);

        return PackageResource::collection($packages);
    }

    /**
     * Create a new bespoke package for the authenticated instructor.
     */
    public function store(StoreInstructorPackageRequest $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        $package = $this->packageService->createPackage($instructor, $request->validated());

        return (new PackageResource($package))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing package owned by the authenticated instructor.
     */
    public function update(UpdateInstructorPackageRequest $request, Package $package): PackageResource
    {
        $instructor = $request->user()->instructor;

        if ($package->instructor_id !== $instructor->id) {
            abort(403, 'You do not own this package.');
        }

        $updated = $this->packageService->updatePackage($package, $request->validated());

        return new PackageResource($updated);
    }
}
