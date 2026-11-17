<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoveSlotsUnderImportedLessonsAction
{
    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * Remove open availability slots (dated today or later) whose own time
     * overlaps an imported, not-cancelled lesson on the same instructor's
     * day. The instructor set the slot up in Drive; the import added the
     * Go Roadie lesson beside it, so the slot showed as bookable online but
     * checkout refused it. Each slot goes through DeleteCalendarItemAction,
     * which also removes its travel block and refuses a slot with a lesson.
     *
     * Kept: slots the import created, slots that are no longer empty
     * availability, and slots with a slot offer. Nothing is created.
     *
     * @return array{counts: array{slots: int, removed: int, travel_blocks: int, instructors: int, skipped_slot_offer: int, skipped_not_empty: int}, dates_by_instructor: array<int, array<string, true>>}
     */
    public function __invoke(bool $dryRun): array
    {
        $counts = ['slots' => 0, 'removed' => 0, 'travel_blocks' => 0, 'instructors' => 0, 'skipped_slot_offer' => 0, 'skipped_not_empty' => 0];
        /** @var array<int, array<string, true>> $datesByInstructor */
        $datesByInstructor = [];

        foreach (array_chunk($this->findSlotIds(), 500) as $chunk) {
            $slots = CalendarItem::query()->with(['calendar', 'travelItem', 'slotOffer'])->whereKey($chunk)->get();

            foreach ($slots as $slot) {
                $counts['slots']++;

                if (! $slot->isEmptyAvailability()) {
                    $counts['skipped_not_empty']++;

                    continue;
                }

                if ($slot->slotOffer !== null) {
                    $counts['skipped_slot_offer']++;

                    continue;
                }

                $hasTravel = $slot->travelItem !== null;

                if (! $dryRun) {
                    DB::transaction(fn () => ($this->deleteCalendarItem)($slot));
                }

                $counts['removed']++;
                $counts['travel_blocks'] += (int) $hasTravel;
                $datesByInstructor[(int) $slot->calendar->instructor_id][$slot->calendar->date->toDateString()] = true;
            }
        }

        $counts['instructors'] = count($datesByInstructor);

        if (! $dryRun && $counts['removed'] > 0) {
            Log::info('import:repair removed open slots under imported lessons', $counts);
        }

        return ['counts' => $counts, 'dates_by_instructor' => $datesByInstructor];
    }

    /**
     * @return array<int, int>
     */
    protected function findSlotIds(): array
    {
        return DB::table('lessons')
            ->join('import_mappings as mapping', fn ($join) => $join
                ->on('mapping.model_id', '=', 'lessons.id')
                ->where('mapping.entity', ImportMapping::ENTITY_LESSON))
            ->join('calendar_items as lesson_item', 'lesson_item.id', '=', 'lessons.calendar_item_id')
            ->join('calendars', 'calendars.id', '=', 'lesson_item.calendar_id')
            ->join('calendar_items as slot', fn ($join) => $join
                ->on('slot.calendar_id', '=', 'lesson_item.calendar_id')
                ->on('slot.id', '<>', 'lesson_item.id')
                ->on('slot.start_time', '<', 'lesson_item.end_time')
                ->on('slot.end_time', '>', 'lesson_item.start_time'))
            ->where('lessons.status', '<>', LessonStatus::CANCELLED->value)
            ->where('calendars.date', '>=', now()->toDateString())
            ->where('slot.item_type', CalendarItemType::Slot->value)
            ->where('slot.is_available', true)
            ->whereNull('slot.status')
            ->whereNotExists(fn ($query) => $query
                ->from('lessons as slot_lesson')
                ->whereColumn('slot_lesson.calendar_item_id', 'slot.id'))
            ->whereNotExists(fn ($query) => $query
                ->from('import_mappings as slot_mapping')
                ->whereColumn('slot_mapping.model_id', 'slot.id')
                ->where('slot_mapping.entity', ImportMapping::ENTITY_DIARY))
            ->distinct()
            ->pluck('slot.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
