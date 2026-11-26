<?php

namespace App\Services;

use App\Actions\Package\CalculatePackagePricingAction;
use App\Actions\Package\CreateAdminPackageAction;
use App\Actions\Package\DeletePackageAction;
use App\Actions\Package\GetAllPackagesAction;
use App\Actions\Package\UpdatePackageAction;
use App\Models\Package;
use App\Support\Discounts;
use Illuminate\Database\Eloquent\Collection;

class PackageService extends BaseService
{
    public function __construct(
        protected GetAllPackagesAction $getAllPackages,
        protected CreateAdminPackageAction $createAdminPackage,
        protected UpdatePackageAction $updatePackage,
        protected DeletePackageAction $deletePackage,
        protected CalculatePackagePricingAction $calculatePricing
    ) {}

    /**
     * Get all platform-owned packages.
     *
     * @return Collection<int, Package>
     */
    public function getAll(): Collection
    {
        return ($this->getAllPackages)();
    }

    /**
     * Create a new admin-level package (no instructor).
     *
     * @param  array{name: string, description: ?string, total_price_pence: int, lessons_count: int}  $data
     */
    public function createAdminPackage(array $data): Package
    {
        return ($this->createAdminPackage)($data);
    }

    /**
     * Update an existing package.
     */
    public function update(Package $package, array $data): Package
    {
        $updated = ($this->updatePackage)($package, $data);

        if ($updated->instructor_id !== null) {
            $this->invalidate(
                $this->cacheKey('instructor', $updated->instructor_id, 'packages')
            );
        }

        return $updated;
    }

    /**
     * Delete a package.
     */
    public function delete(Package $package): void
    {
        ($this->deletePackage)($package);
    }

    /**
     * Calculate full pricing breakdown for a package.
     *
     * @param  array{code?: ?string, type?: string, percentage?: int|float|null, amount_pence?: int|null}|null  $promoDiscount
     * @return array<string, mixed>
     */
    public function calculatePricing(Package $package, ?array $promoDiscount = null): array
    {
        return ($this->calculatePricing)($package, $promoDiscount);
    }

    /**
     * Pricing breakdown for an onboarding enquiry, applying the enquiry's
     * discount code the same way order creation does (to the package price
     * only, never to the fees). The package should already carry any
     * instructor price uplift.
     *
     * @param  array{id?: string, code?: ?string, label?: string, type?: string, percentage?: int|float|null, amount_pence?: int|null}|null  $discount
     * @return array<string, mixed>
     */
    public function calculateEnquiryPricing(Package $package, ?array $discount = null): array
    {
        $promoDiscount = $discount !== null
            ? [
                'code' => Discounts::displayCode($discount),
                'type' => $discount['type'] ?? null,
                'percentage' => $discount['percentage'] ?? null,
                'amount_pence' => $discount['amount_pence'] ?? null,
            ]
            : null;

        return $this->calculatePricing($package, $promoDiscount);
    }
}
