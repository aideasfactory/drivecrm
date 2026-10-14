<?php

namespace App\Services;

use App\Actions\Package\CalculatePackagePricingAction;
use App\Actions\Package\CreateAdminPackageAction;
use App\Actions\Package\DeletePackageAction;
use App\Actions\Package\GetAllPackagesAction;
use App\Actions\Package\UpdatePackageAction;
use App\Models\Package;
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
     * @param  array{code?: string, percentage?: int|float}|null  $promoDiscount
     * @return array<string, mixed>
     */
    public function calculatePricing(Package $package, ?array $promoDiscount = null, ?string $startTime = null, ?string $endTime = null): array
    {
        return ($this->calculatePricing)($package, $promoDiscount, $startTime, $endTime);
    }

    /**
     * Pricing breakdown for an onboarding enquiry, applying the enquiry's
     * discount code the same way order creation does (to the package price
     * only, never to the fees). The package should already carry any
     * instructor price uplift.
     *
     * @param  array{id?: string, label?: string, percentage?: int|float}|null  $discount
     * @return array<string, mixed>
     */
    public function calculateEnquiryPricing(Package $package, ?array $discount = null, ?string $startTime = null, ?string $endTime = null): array
    {
        $promoDiscount = isset($discount['percentage'])
            ? ['code' => $discount['label'] ?? null, 'percentage' => $discount['percentage']]
            : null;

        return $this->calculatePricing($package, $promoDiscount, $startTime, $endTime);
    }
}
