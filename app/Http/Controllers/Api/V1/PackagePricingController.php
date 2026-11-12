<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PackagePricingResource;
use App\Models\Package;
use App\Services\PackageService;
use App\Support\TestPassGuarantee;
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
        if ($request->user()?->isStudent() && ! $package->isVisibleToPupils()) {
            abort(404);
        }

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

        // Mandatory guarantee is part of the order total but is charged with
        // the first payment, so `weekly_payment` stays the spread without it.
        $guaranteePence = TestPassGuarantee::includedChargePence($package);
        $pricing['includes_test_pass_guarantee'] = $guaranteePence > 0;
        $pricing['test_pass_guarantee'] = $guaranteePence / 100;
        $pricing['test_pass_guarantee_pence'] = $guaranteePence;
        $pricing['subtotal'] = round($pricing['subtotal'] + $guaranteePence / 100, 2);
        $pricing['total'] = round($pricing['total'] + $guaranteePence / 100, 2);
        $pricing['total_pence'] += $guaranteePence;

        return new PackagePricingResource($pricing);
    }
}
