<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PackageResource;
use App\Services\InstructorPackageService;
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
}
