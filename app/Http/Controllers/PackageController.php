<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePackageRequest;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function __construct(
        protected PackageService $packageService
    ) {}

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
