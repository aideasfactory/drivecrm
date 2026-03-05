<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAdminPackageAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Create a new admin-level package with Stripe Product and Price.
     *
     * @param  array{name: string, description: ?string, total_price_pence: int, lessons_count: int}  $data
     */
    public function __invoke(array $data): Package
    {
        DB::beginTransaction();

        try {
            $package = Package::create([
                'instructor_id' => null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'total_price_pence' => $data['total_price_pence'],
                'lessons_count' => $data['lessons_count'],
                'active' => true,
            ]);

            $productResult = $this->stripeService->createProduct($package);

            if (! $productResult['success']) {
                throw new \Exception('Failed to create Stripe Product: '.$productResult['error']);
            }

            $package->stripe_product_id = $productResult['product_id'];
            $package->save();

            Log::info('Stripe Product created for admin package', [
                'package_id' => $package->id,
                'stripe_product_id' => $productResult['product_id'],
            ]);

            $priceResult = $this->stripeService->createPrice($package);

            if (! $priceResult['success']) {
                throw new \Exception('Failed to create Stripe Price: '.$priceResult['error']);
            }

            $package->stripe_price_id = $priceResult['price_id'];
            $package->save();

            Log::info('Stripe Price created for admin package', [
                'package_id' => $package->id,
                'stripe_price_id' => $priceResult['price_id'],
            ]);

            DB::commit();

            return $package;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Admin package creation with Stripe sync failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
