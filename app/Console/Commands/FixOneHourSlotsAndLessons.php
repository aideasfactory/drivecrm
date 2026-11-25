<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Enums\OrderSource;
use App\Models\CalendarItem;
use App\Models\Lesson;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FixOneHourSlotsAndLessons extends Command
{
    private const OLD_MINUTES = 60;

    private const NEW_MINUTES = 120;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:fix-one-hour-slots {--dry-run : Report what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off fix: remove future open diary slots shorter than 2 hours (every 1-hour slot, plus shorter slots left by cancelled/banked imported lessons), and make future pending 1-hour lessons on platform packages 2 hours. Imported lessons, practical tests and bespoke packages are left alone. A lesson that would clash is skipped and listed.';

    /** @var list<string> */
    private array $skipped = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $verb = $dryRun ? 'would' : 'will';

        $this->info('Open slots under 2 hours');
        $slotsToDelete = $this->planSlots($verb);

        $this->newLine();
        $this->info('Pending 1-hour lessons on platform packages');
        $lessonsToExtend = $this->planLessons($verb);

        if ($this->skipped !== []) {
            $this->newLine();
            $this->warn('Skipped (needs a manual look):');

            foreach ($this->skipped as $line) {
                $this->warn("  - {$line}");
            }
        }

        $this->newLine();
        $summary = "slots removed: {$slotsToDelete->count()}, lessons extended: {$lessonsToExtend->count()}, skipped: ".count($this->skipped);

        if ($dryRun) {
            $this->info("Dry run — {$summary}. Nothing was saved.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($slotsToDelete, $lessonsToExtend): void {
            foreach ($slotsToDelete as $slot) {
                ($this->deleteCalendarItem)($slot);
            }

            foreach ($lessonsToExtend as $lesson) {
                $this->extendItem($lesson->calendarItem);
                $lesson->update(['end_time' => $this->newEnd($lesson->start_time->format('H:i:s'))]);
            }
        });

        $this->info("Done — {$summary}.");

        return self::SUCCESS;
    }

    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {
        parent::__construct();
    }

    /**
     * Future open slots to remove: every 1-hour slot, plus any slot under 2 hours that the
     * old-system import created (freed when its imported lesson was cancelled or banked).
     * Other short slots (e.g. an instructor's own 90-minute availability) are left alone.
     *
     * @return Collection<int, CalendarItem>
     */
    private function planSlots(string $verb): Collection
    {
        $slots = CalendarItem::query()
            ->with(['calendar', 'travelItem'])
            ->withCount(['lessons', 'slotOffer'])
            ->addSelect(['from_import' => DB::table('import_mappings')
                ->selectRaw('1')
                ->where('entity', 'diary')
                ->whereColumn('model_id', 'calendar_items.id')
                ->limit(1)])
            ->where('item_type', CalendarItemType::Slot)
            ->where('is_available', true)
            ->whereNull('status')
            ->whereHas('calendar', fn ($query) => $query->whereDate('date', '>=', today()))
            ->whereRaw('TIMESTAMPDIFF(MINUTE, start_time, end_time) < ?', [self::NEW_MINUTES])
            ->get()
            ->sortBy(fn (CalendarItem $slot) => [$slot->calendar->instructor_id, $slot->calendar->date])
            ->values();

        $toDelete = collect();

        foreach ($slots as $slot) {
            $label = $this->slotLabel($slot);
            $minutes = (int) Carbon::parse($slot->start_time)->diffInMinutes(Carbon::parse($slot->end_time));
            $fromImport = (bool) $slot->from_import;

            if ($minutes !== self::OLD_MINUTES && ! $fromImport) {
                $this->line("  {$label}: left alone — {$minutes} min, not from the import");

                continue;
            }

            if ($slot->lessons_count > 0 || $slot->slot_offer_count > 0) {
                $this->skipped[] = "{$label}: has a lesson or offer attached";

                continue;
            }

            $toDelete->push($slot);
            $this->line("  {$label}: {$verb} remove".($fromImport ? ' (from import)' : '').($slot->travelItem ? ' + travel' : ''));
        }

        return $toDelete;
    }

    /**
     * Future pending 1-hour lessons on Drive platform packages (bookings team, website, app checkout).
     * Imported orders, bespoke instructor packages and practical tests are excluded.
     *
     * @return Collection<int, Lesson>
     */
    private function planLessons(string $verb): Collection
    {
        $lessons = Lesson::query()
            ->with(['order.package', 'order.student', 'calendarItem.calendar', 'calendarItem.travelItem'])
            ->where('status', LessonStatus::PENDING)
            ->whereDate('date', '>=', today())
            ->whereRaw('TIMESTAMPDIFF(MINUTE, start_time, end_time) = ?', [self::OLD_MINUTES])
            ->whereHas('order', fn ($query) => $query
                ->where('source', '!=', OrderSource::IMPORTED)
                ->whereHas('package', fn ($package) => $package->whereNull('instructor_id')))
            ->whereHas('calendarItem', fn ($query) => $query->where('item_type', CalendarItemType::Slot))
            ->orderBy('order_id')
            ->orderBy('date')
            ->get();

        $toExtend = collect();

        foreach ($lessons as $lesson) {
            $item = $lesson->calendarItem;
            $start = $lesson->start_time->format('H:i:s');
            $end = $lesson->end_time->format('H:i:s');
            $pupil = trim("{$lesson->order->student?->first_name} {$lesson->order->student?->surname}");
            $label = "Lesson #{$lesson->id} ({$pupil}, order #{$lesson->order_id}) on {$lesson->date->format('D j M Y')} {$start}-{$end}";

            if ($item->start_time !== $start || $item->end_time !== $end) {
                $this->skipped[] = "{$label}: diary slot #{$item->id} times don't match the lesson";

                continue;
            }

            $clash = $this->clashAfterExtending($item);

            if ($clash !== null) {
                $this->skipped[] = "{$label}: {$clash}";

                continue;
            }

            $toExtend->push($lesson);
            $this->line("  {$label}: {$verb} extend to {$start}-{$this->newEnd($start)}");
        }

        return $toExtend;
    }

    /**
     * Describe what the 2-hour slot (plus its moved travel block) would overlap, or null if nothing.
     */
    private function clashAfterExtending(CalendarItem $item): ?string
    {
        $travel = $item->travelItem;
        $newEnd = $this->newEnd($item->start_time);
        $newBlockEnd = $travel ? $this->addMinutes($newEnd, $this->travelMinutes($travel)) : $newEnd;

        if ($newBlockEnd > '23:59:00' || $newBlockEnd < $item->start_time) {
            return 'would run past midnight';
        }

        $clashes = CalendarItem::query()
            ->where('calendar_id', $item->calendar_id)
            ->whereKeyNot(array_filter([$item->id, $travel?->id]))
            ->overlapping($item->end_time, $newBlockEnd)
            ->get();

        if ($clashes->isEmpty()) {
            return null;
        }

        return 'would clash with '.$clashes
            ->map(fn (CalendarItem $clash) => "#{$clash->id} {$clash->item_type?->value} {$clash->start_time}-{$clash->end_time}")
            ->implode(', ');
    }

    /**
     * Make the item 2 hours long and move its travel block (if any) to follow it.
     */
    private function extendItem(CalendarItem $item): void
    {
        $newEnd = $this->newEnd($item->start_time);
        $travel = $item->travelItem;

        if ($travel !== null) {
            $travel->update([
                'start_time' => $newEnd,
                'end_time' => $this->addMinutes($newEnd, $this->travelMinutes($travel)),
            ]);
        }

        $item->update(['end_time' => $newEnd]);
    }

    private function newEnd(string $startTime): string
    {
        return $this->addMinutes($startTime, self::NEW_MINUTES);
    }

    private function travelMinutes(CalendarItem $travel): int
    {
        return (int) Carbon::parse($travel->start_time)->diffInMinutes(Carbon::parse($travel->end_time));
    }

    private function addMinutes(string $time, int $minutes): string
    {
        return Carbon::parse($time)->addMinutes($minutes)->format('H:i:s');
    }

    private function slotLabel(CalendarItem $slot): string
    {
        return "Slot #{$slot->id} instructor {$slot->calendar->instructor_id} on {$slot->calendar->date->format('D j M Y')} {$slot->start_time}-{$slot->end_time}";
    }
}
