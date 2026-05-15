<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CalendarItem\ResetDraftCalendarItemsAction;
use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupDraftCalendarItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:cleanup-drafts {--dry-run : Show what would be reset without actually resetting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset draft calendar items created before today back to available';

    /**
     * Execute the console command.
     */
    public function handle(ResetDraftCalendarItemsAction $resetDrafts): int
    {
        $cutoff = now()->startOfDay();

        if ($this->option('dry-run')) {
            $count = CalendarItem::query()
                ->where('status', CalendarItemStatus::DRAFT)
                ->where('created_at', '<', $cutoff)
                ->count();

            $this->info("Would reset {$count} draft calendar item(s) to available.");

            return Command::SUCCESS;
        }

        $reset = $resetDrafts($cutoff);

        Log::info('Nightly draft calendar cleanup completed', [
            'reset_count' => $reset,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        $this->info("Reset {$reset} draft calendar item(s) back to available.");

        return Command::SUCCESS;
    }
}
