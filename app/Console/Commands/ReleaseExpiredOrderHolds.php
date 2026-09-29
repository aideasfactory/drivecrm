<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredOrderHolds extends Command
{
    /**
     * @var string
     */
    protected $signature = 'orders:release-expired-holds';

    /**
     * @var string
     */
    protected $description = 'Release unpaid bookings whose payment window has run out and free their diary slots';

    public function handle(OrderService $orderService): int
    {
        $result = $orderService->releaseExpiredHolds();

        if ($result['released'] > 0 || $result['kept'] > 0) {
            Log::info('Released expired order holds', $result);
        }

        $this->info("Released {$result['released']} unpaid booking(s); kept {$result['kept']}.");

        return Command::SUCCESS;
    }
}
