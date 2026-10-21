<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Calendar\FindBlockingCalendarItemsAction;
use App\Models\Lesson;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class FlagProvisionalLessonsAction
{
    public function __construct(
        protected FindBlockingCalendarItemsAction $findBlockingCalendarItems,
    ) {}

    /**
     * Flag a new booking's lessons as provisional where their diary slot clashes
     * with something already in the diary. Only the new booking is flagged;
     * whatever it clashes with is left untouched. Run once, straight after the
     * order's lessons are created.
     *
     * @return int The number of lessons flagged
     */
    public function __invoke(Order $order): int
    {
        $lessons = $order->lessons()
            ->whereNotNull('calendar_item_id')
            ->with('calendarItem')
            ->get();

        $flaggedIds = $lessons
            ->filter(fn (Lesson $lesson): bool => $lesson->calendarItem !== null
                && ($this->findBlockingCalendarItems)($lesson->calendarItem)->isNotEmpty())
            ->pluck('id');

        if ($flaggedIds->isEmpty()) {
            return 0;
        }

        Lesson::query()->whereKey($flaggedIds)->update(['provisional_at' => now()]);

        Log::info('Booking has provisional (clashing) lessons', [
            'order_id' => $order->id,
            'lesson_ids' => $flaggedIds->all(),
        ]);

        return $flaggedIds->count();
    }
}
