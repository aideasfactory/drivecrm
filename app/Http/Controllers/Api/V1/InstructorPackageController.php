<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInstructorPackageRequest;
use App\Http\Requests\Api\V1\UpdateInstructorPackageRequest;
use App\Http\Resources\V1\PackageResource;
use App\Models\Package;
use App\Services\InstructorPackageService;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorPackageController extends Controller
{
    public function __construct(
        protected InstructorPackageService $packageService,
        protected InstructorService $instructorService
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

    /**
     * Remove (hide) a package owned by the authenticated instructor. Same
     * soft deactivation as the web "Remove Package" action: the row and its
     * orders, lessons and payments are kept, and it can be restored on the web.
     */
    public function destroy(Request $request, Package $package): PackageResource
    {
        $instructor = $request->user()->instructor;

        if ($instructor === null || $package->instructor_id !== $instructor->id) {
            abort(403, 'You do not own this package.');
        }

        $updated = $this->instructorService->deactivatePackage($package);

        return (new PackageResource($updated))->additional(['message' => 'Package removed.']);
    }
}
