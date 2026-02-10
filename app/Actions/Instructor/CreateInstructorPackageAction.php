<?php

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Package;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateInstructorPackageAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Create a new bespoke package for an instructor.
     */
    public function __invoke(Instructor $instructor, array $data): Package
    {
        // Check if instructor has completed Stripe onboarding
        if (! $instructor->hasCompletedOnboarding() || ! $instructor->charges_enabled) {
            throw new \Exception('Instructor must complete Stripe Connect onboarding before creating packages.');
        }

        DB::beginTransaction();

        try {
            // 1. Create package in database
            $package = Package::create([
                'instructor_id' => $instructor->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'total_price_pence' => $data['total_price_pence'],
                'lessons_count' => $data['lessons_count'],
                'active' => true,
            ]);

            // 2. Create Stripe Product
            $productResult = $this->stripeService->createProduct($package);

            if (! $productResult['success']) {
                throw new \Exception('Failed to create Stripe Product: '.$productResult['error']);
            }

            $package->stripe_product_id = $productResult['product_id'];
            $package->save();

            Log::info('Stripe Product created for package', [
                'package_id' => $package->id,
                'stripe_product_id' => $productResult['product_id'],
            ]);

            // 3. Create Stripe Price
            $priceResult = $this->stripeService->createPrice($package);

            if (! $priceResult['success']) {
                throw new \Exception('Failed to create Stripe Price: '.$priceResult['error']);
            }

            $package->stripe_price_id = $priceResult['price_id'];
            $package->save();

            Log::info('Stripe Price created for package', [
                'package_id' => $package->id,
                'stripe_price_id' => $priceResult['price_id'],
            ]);

            DB::commit();

            return $package;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Package creation with Stripe sync failed', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
