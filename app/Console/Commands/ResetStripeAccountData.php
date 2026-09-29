<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Instructor;
use App\Models\Package;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ResetStripeAccountData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stripe:reset-account-data
        {--dry-run : Show what would change without changing anything}';

    /**
     * @var string
     */
    protected $description = 'One-off reset after switching Stripe accounts: clear pupil and instructor Stripe data, remove instructor packages and re-create Drive platform packages on the new account';

    public function handle(StripeService $stripeService): int
    {
        $currentAccountId = $this->currentStripeAccountId();

        if ($currentAccountId === null) {
            return Command::FAILURE;
        }

        $this->info("Current Stripe keys belong to: {$currentAccountId}");

        $instructorPackages = Package::query()->whereNotNull('instructor_id')->withCount('orders')->get();
        $packagesToDelete = $instructorPackages->where('orders_count', 0);
        $packagesToDeactivate = $instructorPackages->where('orders_count', '>', 0);
        $platformPackages = Package::query()->whereNull('instructor_id')->get();

        $this->table(['Change', 'Count'], [
            ['Users: clear stripe_customer_id', User::query()->whereNotNull('stripe_customer_id')->count()],
            ['Instructors: clear Stripe connection', Instructor::query()->whereNotNull('stripe_account_id')->count()],
            ['Instructor packages: delete (no orders)', $packagesToDelete->count()],
            ['Instructor packages: deactivate (have orders, kept for history)', $packagesToDeactivate->count()],
            ['Drive platform packages: new Stripe product + price (active only)', $platformPackages->where('active', true)->count()],
        ]);

        if ($this->option('dry-run')) {
            $this->warn('Dry run. Nothing was changed.');

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($packagesToDelete, $packagesToDeactivate): void {
            User::query()->whereNotNull('stripe_customer_id')->update(['stripe_customer_id' => null]);

            Instructor::query()->update([
                'stripe_account_id' => null,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'onboarding_complete' => false,
            ]);

            Package::query()->whereIn('id', $packagesToDeactivate->pluck('id'))->update([
                'active' => false,
                'stripe_product_id' => null,
                'stripe_price_id' => null,
            ]);

            Package::query()->whereIn('id', $packagesToDelete->pluck('id'))->delete();

            Package::query()->whereNull('instructor_id')->update([
                'stripe_product_id' => null,
                'stripe_price_id' => null,
            ]);
        });

        $this->info('Database reset complete.');

        $failed = 0;

        foreach ($platformPackages->where('active', true) as $package) {
            $package->refresh();

            $productResult = $stripeService->createProduct($package);

            if (! $productResult['success']) {
                $this->error("Package #{$package->id} {$package->name}: product failed — {$productResult['error']}");
                $failed++;

                continue;
            }

            $package->update(['stripe_product_id' => $productResult['product_id']]);

            $priceResult = $stripeService->createPrice($package);

            if (! $priceResult['success']) {
                $this->error("Package #{$package->id} {$package->name}: price failed — {$priceResult['error']}");
                $failed++;

                continue;
            }

            $package->update(['stripe_price_id' => $priceResult['price_id']]);

            $this->line("Package #{$package->id} {$package->name}: {$productResult['product_id']} / {$priceResult['price_id']}");
        }

        Log::info('Stripe account data reset', [
            'stripe_account_id' => $currentAccountId,
            'instructor_packages_deleted' => $packagesToDelete->count(),
            'instructor_packages_deactivated' => $packagesToDeactivate->count(),
            'platform_package_failures' => $failed,
        ]);

        if ($failed > 0) {
            $this->warn("{$failed} platform package(s) failed. Safe to re-run: the database steps just repeat and packages are re-created.");

            return Command::FAILURE;
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }

    /**
     * Look up which Stripe account the configured secret key belongs to.
     */
    protected function currentStripeAccountId(): ?string
    {
        try {
            return (new StripeClient(config('services.stripe.secret')))->accounts->retrieve()->id;
        } catch (ApiErrorException $e) {
            $this->error('Could not reach Stripe with the current keys: '.$e->getMessage());

            return null;
        }
    }
}
