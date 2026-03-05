<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function __construct(
        protected PackageService $packageService
    ) {}

    /**
     * Display all packages.
     */
    public function index(): Response
    {
        $packages = $this->packageService->getAll();

        return Inertia::render('Packages/Index', [
            'packages' => $packages->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'total_price_pence' => $package->total_price_pence,
                'lessons_count' => $package->lessons_count,
                'lesson_price_pence' => $package->lesson_price_pence,
                'formatted_total_price' => $package->formatted_total_price,
                'formatted_lesson_price' => $package->formatted_lesson_price,
                'active' => $package->active,
                'is_platform_package' => $package->isPlatformPackage(),
                'instructor_name' => $package->instructor?->name,
                'created_at' => $package->created_at?->format('d M Y'),
            ]),
        ]);
    }

    /**
     * Store a new admin-level package.
     */
    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packageService->createAdminPackage($request->validated());

        return response()->json([
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'total_price_pence' => $package->total_price_pence,
                'lessons_count' => $package->lessons_count,
                'lesson_price_pence' => $package->lesson_price_pence,
                'formatted_total_price' => $package->formatted_total_price,
                'formatted_lesson_price' => $package->formatted_lesson_price,
                'active' => $package->active,
                'is_platform_package' => $package->isPlatformPackage(),
            ],
        ], 201);
    }

    /**
     * Update an existing package.
     */
    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $updatedPackage = $this->packageService->update($package, $request->validated());

        return response()->json([
            'package' => [
                'id' => $updatedPackage->id,
                'name' => $updatedPackage->name,
                'description' => $updatedPackage->description,
                'total_price_pence' => $updatedPackage->total_price_pence,
                'lessons_count' => $updatedPackage->lessons_count,
                'lesson_price_pence' => $updatedPackage->lesson_price_pence,
                'formatted_total_price' => $updatedPackage->formatted_total_price,
                'formatted_lesson_price' => $updatedPackage->formatted_lesson_price,
                'active' => $updatedPackage->active,
                'is_platform_package' => $updatedPackage->isPlatformPackage(),
                'is_bespoke_package' => $updatedPackage->isBespokePackage(),
            ],
        ]);
    }
}
