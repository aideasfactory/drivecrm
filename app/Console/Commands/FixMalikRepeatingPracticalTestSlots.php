<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CalendarItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FixMalikRepeatingPracticalTestSlots extends Command
{
    /**
     * Malik Arshad's (instructor 128) weekly Monday series that was created as a repeating
     * practical test in the app, so it was saved as 1-hour available slots instead of 2-hour ones.
     */
    private const RECURRENCE_GROUP_ID = '71ad4dd7-440f-4215-8994-05f06e4b868f';

    private const INSTRUCTOR_ID = 128;

    private const SLOT_START = '08:00:00';

    private const OLD_SLOT_END = '09:00:00';

    private const NEW_SLOT_END = '10:00:00';

    private const OLD_TRAVEL = ['09:00:00', '09:30:00'];

    private const NEW_TRAVEL = ['10:00:00', '10:30:00'];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:fix-malik-monday-slots {--dry-run : Check every slot and report what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off fix: extend Malik Arshad\'s weekly Monday 08:00-09:00 slots to 08:00-10:00 and move their travel to 10:00-10:30. Refuses to change anything if any slot is booked or would clash.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /** @var Collection<int, CalendarItem> $slots */
        $slots = CalendarItem::query()
            ->with(['calendar', 'travelItem'])
            ->withCount(['lessons', 'slotOffer'])
            ->where('recurrence_group_id', self::RECURRENCE_GROUP_ID)
            ->get()
            ->sortBy(fn (CalendarItem $slot) => $slot->calendar?->date)
            ->values();

        if ($slots->isEmpty()) {
            $this->error('No slots found for this series. Nothing to do.');

            return self::FAILURE;
        }

        $toFix = collect();
        $alreadyFixed = 0;
        $problems = [];

        foreach ($slots as $slot) {
            $label = $this->label($slot);
            $problem = $this->findProblem($slot);

            if ($problem === 'already fixed') {
                $alreadyFixed++;
                $this->line("{$label}: already 08:00-10:00 — skipped");

                continue;
            }

            if ($problem !== null) {
                $problems[] = "{$label}: {$problem}";

                continue;
            }

            $toFix->push($slot);
            $this->line("{$label}: ".($dryRun ? 'would change' : 'will change').' 08:00-09:00 → 08:00-10:00, travel 09:00-09:30 → 10:00-10:30');
        }

        if ($problems !== []) {
            $this->newLine();
            $this->error('Nothing was changed. These slots need checking first:');

            foreach ($problems as $problem) {
                $this->warn("  - {$problem}");
            }

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: would fix {$toFix->count()} slot(s). Already fixed: {$alreadyFixed}. No clashes found.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toFix): void {
            foreach ($toFix as $slot) {
                $slot->travelItem->update([
                    'start_time' => self::NEW_TRAVEL[0],
                    'end_time' => self::NEW_TRAVEL[1],
                ]);

                $slot->update(['end_time' => self::NEW_SLOT_END]);
            }
        });

        $this->info("Fixed {$toFix->count()} slot(s). Already fixed: {$alreadyFixed}.");

        return self::SUCCESS;
    }

    /**
     * Return why a slot can't be fixed, 'already fixed' if it has been done, or null if it's safe to fix.
     */
    private function findProblem(CalendarItem $slot): ?string
    {
        if ($slot->calendar?->instructor_id !== self::INSTRUCTOR_ID) {
            return 'not on Malik Arshad\'s diary';
        }

        if ($slot->start_time === self::SLOT_START && $slot->end_time === self::NEW_SLOT_END) {
            return 'already fixed';
        }

        if ($slot->start_time !== self::SLOT_START || $slot->end_time !== self::OLD_SLOT_END) {
            return "time has changed to {$slot->start_time}-{$slot->end_time}";
        }

        if ($slot->status !== null || ! $slot->is_available || $slot->lessons_count > 0 || $slot->slot_offer_count > 0) {
            return 'slot is no longer free (booked, held, offered or marked unavailable)';
        }

        $travel = $slot->travelItem;

        if ($travel === null || $travel->start_time !== self::OLD_TRAVEL[0] || $travel->end_time !== self::OLD_TRAVEL[1]) {
            return 'travel block is missing or has moved';
        }

        $clashes = CalendarItem::query()
            ->where('calendar_id', $slot->calendar_id)
            ->whereKeyNot([$slot->id, $travel->id])
            ->overlapping(self::OLD_SLOT_END, self::NEW_TRAVEL[1])
            ->get();

        if ($clashes->isNotEmpty()) {
            $details = $clashes
                ->map(fn (CalendarItem $item) => "#{$item->id} {$item->item_type?->value} {$item->start_time}-{$item->end_time}")
                ->implode(', ');

            return "clashes with {$details}";
        }

        return null;
    }

    private function label(CalendarItem $slot): string
    {
        return "Slot #{$slot->id} on ".$slot->calendar?->date?->format('D j M Y');
    }
}
