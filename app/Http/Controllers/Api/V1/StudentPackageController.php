<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PackageResource;
use App\Services\InstructorPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentPackageController extends Controller
{
    public function __construct(
        protected InstructorPackageService $packageService
    ) {}

    /**
     * Return active packages belonging to the authenticated student's attached instructor.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Student profile not found for the authenticated user.',
            ], 404);
        }

        if (! $student->instructor_id) {
            return response()->json([
                'message' => 'You must be attached to an instructor before you can view packages.',
            ], 422);
        }

        $packages = $this->packageService->getPackages($student->instructor);

        return PackageResource::collection($packages);
    }
}
