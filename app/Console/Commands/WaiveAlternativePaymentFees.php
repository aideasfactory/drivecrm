<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AlternativePaymentService;
use Illuminate\Console\Command;

class WaiveAlternativePaymentFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:waive-alternative-fees
        {--dry-run : List the orders that would change without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off clean-up: remove the booking and digital fees from pay-in-full orders paid to the instructor by an alternative method (no fees were charged). Weekly orders are never changed. Safe to re-run.';

    public function handle(AlternativePaymentService $alternativePaymentService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changes = $alternativePaymentService->waiveFeesOnAlternativeOrders($dryRun);

        if ($changes === []) {
            $this->line('Nothing to change.');

            return self::SUCCESS;
        }

        $this->table(
            ['Order', 'Booking fee', 'Digital fee', 'Total before', 'Total after'],
            array_map(fn (array $change): array => [
                $change['order_id'],
                $this->pounds($change['booking_fee_pence']),
                $this->pounds($change['digital_fee_pence']),
                $this->pounds($change['total_before_pence']),
                $this->pounds($change['total_after_pence']),
            ], $changes),
        );

        $feesPence = array_sum(array_column($changes, 'booking_fee_pence')) + array_sum(array_column($changes, 'digital_fee_pence'));

        $this->line(sprintf(
            '%s %d order(s), %s of fees removed.',
            $dryRun ? 'Would change' : 'Changed',
            count($changes),
            $this->pounds($feesPence),
        ));

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }

    protected function pounds(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }
}
