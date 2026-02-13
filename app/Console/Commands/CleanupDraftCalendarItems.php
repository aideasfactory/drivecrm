<?php

namespace App\Console\Commands;

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
    protected $signature = 'calendar:cleanup-drafts {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete draft calendar items that were created before today (abandoned bookings)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🧹 Starting cleanup of draft calendar items...');
        $this->newLine();

        // Find all draft calendar items created before today
        $query = CalendarItem::where('status', CalendarItemStatus::DRAFT)
            ->where('created_at', '<', now()->startOfDay());

        $count = $query->count();

        if ($count === 0) {
            $this->info('✅ No draft calendar items to clean up.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$count} draft calendar items to delete.");

        if ($isDryRun) {
            $this->newLine();
            $this->info('🔍 DRY RUN - Showing items that would be deleted:');
            $this->newLine();

            $items = $query->with('calendar.instructor.user')->get();

            $this->table(
                ['ID', 'Instructor', 'Date', 'Time Slot', 'Created At'],
                $items->map(fn ($item) => [
                    $item->id,
                    $item->calendar->instructor->user->name ?? 'N/A',
                    $item->calendar->date ?? 'N/A',
                    "{$item->start_time} - {$item->end_time}",
                    $item->created_at->format('Y-m-d H:i:s'),
                ])
            );

            $this->newLine();
            $this->info('ℹ️  Run without --dry-run to actually delete these items.');

            return Command::SUCCESS;
        }

        // Confirm deletion
        if (! $this->confirm("Delete {$count} draft calendar items?", true)) {
            $this->warn('Cleanup cancelled.');

            return Command::FAILURE;
        }

        // Delete the items
        $deleted = $query->delete();

        Log::info('Cleaned up draft calendar items', [
            'deleted_count' => $deleted,
            'cutoff_date' => now()->startOfDay()->toDateTimeString(),
        ]);

        $this->info("✅ Deleted {$deleted} draft calendar items.");

        return Command::SUCCESS;
    }
}
