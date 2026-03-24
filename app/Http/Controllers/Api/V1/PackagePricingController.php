<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PackagePricingResource;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\Request;

class PackagePricingController extends Controller
{
    public function __construct(
        protected PackageService $packageService
    ) {}

    /**
     * Return full pricing breakdown for a package.
     */
    public function show(Request $request, Package $package): PackagePricingResource
    {
        $promoDiscount = null;

        if ($request->filled('promo_code')) {
            $promoCode = strtolower($request->input('promo_code'));

            $promoPercentage = match ($promoCode) {
                'save10' => 10,
                'save20' => 20,
                default => null,
            };

            if ($promoPercentage !== null) {
                $promoDiscount = [
                    'code' => strtoupper($request->input('promo_code')),
                    'percentage' => $promoPercentage,
                ];
            }
        }

        $pricing = $this->packageService->calculatePricing($package, $promoDiscount);

        return new PackagePricingResource($pricing);
    }
}
