<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Instructor\UpdateCalendarItemAction;
use App\Enums\LessonStatus;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\LessonRescheduling\LessonsBulkRescheduledInstructorNotification;
use App\Notifications\LessonRescheduling\LessonsBulkRescheduledStudentNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MoveLessonAndFutureSiblingsAction
{
    public function __construct(
        protected UpdateCalendarItemAction $updateCalendarItem,
    ) {}

    /**
     * Move the lesson on the given anchor calendar item to the new date/time, and
     * snap every future un-signed-off lesson in the same order to the same
     * day-of-week and time at weekly intervals from the anchor.
     *
     * @return array{
     *     anchor_item: CalendarItem,
     *     anchor_lesson: Lesson,
     *     moved_siblings: Collection<int, Lesson>,
     *     moved_count: int,
     *     old_date: string,
     *     old_start_time: string,
     *     old_end_time: string,
     * }
     */
    public function __invoke(
        Instructor $instructor,
        CalendarItem $anchorItem,
        string $newDate,
        string $newStartTime,
        string $newEndTime,
        User $admin,
    ): array {
        $anchorItem->loadMissing(['calendar', 'lessons.order.student.user', 'lessons.payout']);

        $anchorLesson = $anchorItem->lessons->first();

        if (! $anchorLesson) {
            throw new RuntimeException('Anchor calendar item has no lesson — bulk reschedule requires a lesson booking.');
        }

        if (! $anchorLesson->order_id) {
            throw new RuntimeException('Anchor lesson has no order — bulk reschedule requires a booking context.');
        }

        $anchorOriginalDate = $anchorLesson->date->copy();
        $oldDateString = $anchorItem->calendar->date->format('Y-m-d');
        $oldStartTime = (string) $anchorItem->start_time;
        $oldEndTime = (string) $anchorItem->end_time;

        $futureSiblings = Lesson::query()
            ->where('order_id', $anchorLesson->order_id)
            ->where('id', '!=', $anchorLesson->id)
            ->where('date', '>', $anchorOriginalDate->format('Y-m-d'))
            ->where('status', '!=', LessonStatus::COMPLETED)
            ->whereDoesntHave('payout')
            ->with(['calendarItem.calendar', 'order.student.user'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $newAnchorDate = Carbon::parse($newDate);

        DB::transaction(function () use ($instructor, $anchorItem, $newDate, $newStartTime, $newEndTime, $futureSiblings, $newAnchorDate): void {
            ($this->updateCalendarItem)($instructor, $anchorItem, $newDate, $newStartTime, $newEndTime);

            foreach ($futureSiblings as $index => $sibling) {
                if (! $sibling->calendarItem) {
                    continue;
                }

                $siblingNewDate = $newAnchorDate->copy()->addWeeks($index + 1)->format('Y-m-d');

                ($this->updateCalendarItem)(
                    $instructor,
                    $sibling->calendarItem,
                    $siblingNewDate,
                    $newStartTime,
                    $newEndTime,
                );
            }
        });

        $anchorItem->refresh()->load('calendar');
        $anchorLesson->refresh();

        $movedSiblings = $futureSiblings->fresh(['calendarItem.calendar']);

        $student = $anchorLesson->order?->student;
        $studentUser = $student?->user;

        if ($student && $studentUser) {
            $totalMoved = 1 + $movedSiblings->count();

            $studentUser->notify(new LessonsBulkRescheduledStudentNotification(
                $student,
                $instructor,
                $totalMoved,
                $newAnchorDate->format('Y-m-d'),
                $newStartTime,
                $newEndTime,
            ));

            $student->logActivity(
                "Rescheduled {$totalMoved} upcoming lesson(s) in booking #{$anchorLesson->order_id} from {$oldDateString} {$oldStartTime} to {$newDate} {$newStartTime}",
                'notification',
                [
                    'order_id' => $anchorLesson->order_id,
                    'anchor_lesson_id' => $anchorLesson->id,
                    'affected_lesson_ids' => $movedSiblings->pluck('id')->prepend($anchorLesson->id)->all(),
                    'old_date' => $oldDateString,
                    'old_start_time' => $oldStartTime,
                    'old_end_time' => $oldEndTime,
                    'new_date' => $newDate,
                    'new_start_time' => $newStartTime,
                    'new_end_time' => $newEndTime,
                    'moved_by_user_id' => $admin->id,
                ],
            );
        }

        $instructor->loadMissing('user');

        if ($instructor->user) {
            $studentName = $student
                ? trim("{$student->first_name} {$student->surname}")
                : 'a student';

            $totalMoved = 1 + $movedSiblings->count();

            $instructor->user->notify(new LessonsBulkRescheduledInstructorNotification(
                $instructor,
                $student,
                $totalMoved,
                $newAnchorDate->format('Y-m-d'),
                $newStartTime,
                $newEndTime,
            ));

            $instructor->logActivity(
                "Rescheduled {$totalMoved} upcoming lesson(s) for {$studentName} from {$oldDateString} {$oldStartTime} to {$newDate} {$newStartTime}",
                'notification',
                [
                    'order_id' => $anchorLesson->order_id,
                    'anchor_lesson_id' => $anchorLesson->id,
                    'affected_lesson_ids' => $movedSiblings->pluck('id')->prepend($anchorLesson->id)->all(),
                    'old_date' => $oldDateString,
                    'old_start_time' => $oldStartTime,
                    'old_end_time' => $oldEndTime,
                    'new_date' => $newDate,
                    'new_start_time' => $newStartTime,
                    'new_end_time' => $newEndTime,
                    'moved_by_user_id' => $admin->id,
                ],
            );
        }

        return [
            'anchor_item' => $anchorItem,
            'anchor_lesson' => $anchorLesson,
            'moved_siblings' => $movedSiblings,
            'moved_count' => 1 + $movedSiblings->count(),
            'old_date' => $oldDateString,
            'old_start_time' => $oldStartTime,
            'old_end_time' => $oldEndTime,
        ];
    }
}
