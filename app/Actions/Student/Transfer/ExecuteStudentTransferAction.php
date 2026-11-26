<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Actions\Calendar\CancelBookingAction;
use App\Actions\Calendar\TakeOverOpenAvailabilityAction;
use App\Actions\Lesson\SendLessonToRebookAction;
use App\Actions\Student\Lesson\GetUnscheduledLessonsSummaryAction;
use App\Enums\LessonCancellationReason;
use App\Enums\TransferLessonDecision;
use App\Enums\TransferReason;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentTransfer\StudentGainedNotification;
use App\Notifications\StudentTransfer\StudentLostNotification;
use App\Notifications\StudentTransfer\StudentTransferToStudentNotification;
use App\Services\InstructorCalendarService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExecuteStudentTransferAction
{
    public function __construct(
        protected PreviewStudentTransferAction $previewTransfer,
        protected CancelBookingAction $cancelBooking,
        protected SendLessonToRebookAction $sendLessonToRebook,
        protected TakeOverOpenAvailabilityAction $takeOverOpenAvailability,
        protected GetUnscheduledLessonsSummaryAction $getUnscheduledLessonsSummary,
    ) {}

    /**
     * Move a pupil to a new instructor, applying the Pupil Transfer Check
     * (see `PreviewStudentTransferAction`):
     *
     * 1. Bookings still waiting for their first payment are cancelled (payment
     *    page closed first). If that fails, nothing else happens.
     * 2. Future lessons whose time is free move into the new diary as they are,
     *    and lessons already unscheduled follow the pupil.
     * 3. Past lessons Head Office marked missed, and future lessons that clash
     *    with the new diary, become unscheduled with the new instructor (paid
     *    or owed exactly as before). Past lessons marked happened stay with the
     *    old instructor to sign off. If one can't be made unscheduled, a clash
     *    moves into the new diary anyway (flagged) and a missed lesson stays;
     *    both are reported so Head Office can sort them out.
     * 4. Audit logs on all three parties, and one email each to the pupil, the
     *    old instructor and the new instructor.
     *
     * @param  array<int|string, TransferLessonDecision|string>  $pastLessonDecisions  Lesson ID => decision, one per past lesson not signed off
     * @return array{
     *     student: Student,
     *     source_instructor: Instructor,
     *     destination_instructor: Instructor,
     *     moved_lessons: Collection<int, Lesson>,
     *     clashing_lessons: Collection<int, Lesson>,
     *     unscheduled_lessons: Collection<int, Lesson>,
     *     missed_lessons: Collection<int, Lesson>,
     *     cancelled_orders: Collection<int, Order>,
     *     problems: list<string>,
     * }
     *
     * @throws RuntimeException When the pupil can't be transferred
     */
    public function __invoke(
        Student $student,
        Instructor $destination,
        User $admin,
        TransferReason $reason,
        ?string $notes = null,
        array $pastLessonDecisions = [],
    ): array {
        $destination->loadMissing('user');

        $preview = ($this->previewTransfer)($student, $destination);
        $sourceInstructor = $preview['source'];

        if ($sourceInstructor->id === $destination->id) {
            throw new RuntimeException('The destination instructor is the same as the current instructor.');
        }

        $decisions = collect($pastLessonDecisions)->mapWithKeys(fn (TransferLessonDecision|string $decision, int|string $lessonId): array => [
            (int) $lessonId => $decision instanceof TransferLessonDecision ? $decision : TransferLessonDecision::from($decision),
        ]);

        if ($preview['past_unsigned']->contains(fn (Lesson $lesson): bool => ! $decisions->has($lesson->id))) {
            throw new RuntimeException('Say whether each past lesson took place before moving the pupil.');
        }

        $sourceName = $sourceInstructor->name ?? "Instructor #{$sourceInstructor->id}";
        $destinationName = $destination->name ?? "Instructor #{$destination->id}";

        // Described now: a lesson made unscheduled loses its date.
        $descriptions = $preview['past_unsigned']
            ->concat($preview['future_clashes']->pluck('lesson'))
            ->mapWithKeys(fn (Lesson $lesson): array => [$lesson->id => $this->describe($lesson)]);

        $cancelledOrders = $this->cancelUnpaidBookings($preview['pending_orders'], $destinationName, $admin);

        $clashesToUnschedule = $preview['future_clashes']->where('can_unschedule', true)->pluck('lesson');
        $clashingLessons = $preview['future_clashes']->where('can_unschedule', false)->pluck('lesson');
        $movedLessons = $preview['future_free']->concat($clashingLessons);

        DB::transaction(function () use ($student, $sourceInstructor, $destination, $movedLessons): void {
            foreach ($movedLessons as $lesson) {
                $this->moveIntoDiary($lesson, $destination);
            }

            Lesson::query()
                ->banked()
                ->where('instructor_id', $sourceInstructor->id)
                ->whereHas('order', fn ($q) => $q->where('student_id', $student->id))
                ->update(['instructor_id' => $destination->id]);

            $student->update(['instructor_id' => $destination->id]);
        });

        $problems = [];
        $unscheduledLessons = collect();
        $missedLessons = collect();

        $missed = $preview['past_unsigned']->filter(fn (Lesson $lesson): bool => $decisions[$lesson->id] === TransferLessonDecision::Missed);

        foreach ($missed as $lesson) {
            try {
                $missedLessons->push(($this->sendLessonToRebook)(
                    $lesson,
                    LessonCancellationReason::InstructorUnavailable,
                    "Missed by {$sourceName}. Moved to {$destinationName} to schedule when the pupil changed instructor",
                    $destination,
                    $admin,
                    false,
                ));
            } catch (Throwable $e) {
                $problems[] = "The missed lesson on {$descriptions[$lesson->id]} stayed with {$sourceName}: {$e->getMessage()}";
                Log::warning('Transfer: missed lesson not made unscheduled', ['lesson_id' => $lesson->id, 'error' => $e->getMessage()]);
            }
        }

        foreach ($clashesToUnschedule as $lesson) {
            try {
                $unscheduledLessons->push(($this->sendLessonToRebook)(
                    $lesson,
                    LessonCancellationReason::Other,
                    "Clashed with {$destinationName}'s diary when the pupil changed instructor",
                    $destination,
                    $admin,
                    false,
                ));
            } catch (Throwable $e) {
                DB::transaction(fn () => $this->moveIntoDiary($lesson, $destination));
                $clashingLessons->push($lesson);
                $movedLessons->push($lesson);
                $problems[] = "The lesson on {$descriptions[$lesson->id]} clashes in {$destinationName}'s diary and was moved there anyway: {$e->getMessage()}";
                Log::warning('Transfer: clashing lesson not made unscheduled', ['lesson_id' => $lesson->id, 'error' => $e->getMessage()]);
            }
        }

        $this->invalidateCalendarCaches($movedLessons, $sourceInstructor, $destination);

        $this->recordAndNotify(
            $student,
            $sourceInstructor,
            $destination,
            $admin,
            $reason,
            $notes,
            $movedLessons,
            $clashingLessons,
            $unscheduledLessons,
            $missedLessons,
            $missedLessons->map(fn (Lesson $lesson): string => $descriptions[$lesson->id])->values()->all(),
            $cancelledOrders,
        );

        return [
            'student' => $student->fresh(['instructor.user']),
            'source_instructor' => $sourceInstructor,
            'destination_instructor' => $destination,
            'moved_lessons' => $movedLessons,
            'clashing_lessons' => $clashingLessons,
            'unscheduled_lessons' => $unscheduledLessons,
            'missed_lessons' => $missedLessons,
            'cancelled_orders' => $cancelledOrders,
            'problems' => $problems,
        ];
    }

    /**
     * Cancel each booking still waiting for its first payment, through the
     * normal cancel (closes its payment page first, so it can't be paid).
     *
     * @param  Collection<int, Order>  $pendingOrders
     * @return Collection<int, Order>
     *
     * @throws RuntimeException When a booking can't be cancelled
     */
    protected function cancelUnpaidBookings(Collection $pendingOrders, string $destinationName, User $admin): Collection
    {
        $cancelled = collect();

        foreach ($pendingOrders as $order) {
            $diaryItem = $order->lessons()->whereNotNull('calendar_item_id')->with('calendarItem')->first()?->calendarItem;

            if (! $diaryItem) {
                Log::warning('Transfer: unpaid booking has no diary item to cancel through', ['order_id' => $order->id]);

                continue;
            }

            try {
                ($this->cancelBooking)(
                    $diaryItem,
                    LessonCancellationReason::Other,
                    "Pupil moved to {$destinationName} before this booking was paid",
                    false,
                    $admin,
                );
            } catch (RuntimeException $e) {
                throw new RuntimeException("The unpaid booking #{$order->id} couldn't be cancelled, so the pupil hasn't been moved: {$e->getMessage()}", previous: $e);
            }

            $cancelled->push($order);
        }

        return $cancelled;
    }

    /**
     * Put a lesson's diary item (and its travel block) on the new
     * instructor's diary for the same day, taking over any open availability
     * it lands on.
     */
    protected function moveIntoDiary(Lesson $lesson, Instructor $destination): void
    {
        $calendarItem = $lesson->calendar_item_id
            ? CalendarItem::with('travelItem')->find($lesson->calendar_item_id)
            : null;

        if ($calendarItem) {
            $destinationCalendar = Calendar::firstOrCreate([
                'instructor_id' => $destination->id,
                'date' => $lesson->date->toDateString(),
            ]);

            $calendarItem->update(['calendar_id' => $destinationCalendar->id]);
            $calendarItem->travelItem?->update(['calendar_id' => $destinationCalendar->id]);

            ($this->takeOverOpenAvailability)($calendarItem->fresh());
        }

        $lesson->update(['instructor_id' => $destination->id]);
    }

    /**
     * @param  Collection<int, Lesson>  $movedLessons
     */
    protected function invalidateCalendarCaches(Collection $movedLessons, Instructor $source, Instructor $destination): void
    {
        $calendarService = app(InstructorCalendarService::class);

        $movedLessons
            ->map(fn (Lesson $lesson): ?string => $lesson->date?->toDateString())
            ->filter()
            ->unique()
            ->each(function (string $date) use ($calendarService, $source, $destination): void {
                $calendarService->invalidateCalendarCache($source->id, $date);
                $calendarService->invalidateCalendarCache($destination->id, $date);
            });
    }

    /**
     * @param  Collection<int, Lesson>  $movedLessons
     * @param  Collection<int, Lesson>  $clashingLessons  Moved into the new diary despite a clash
     * @param  Collection<int, Lesson>  $unscheduledClashes  Future lessons made unscheduled because they clashed
     * @param  Collection<int, Lesson>  $missedLessons
     * @param  list<string>  $missedDescriptions
     * @param  Collection<int, Order>  $cancelledOrders
     */
    protected function recordAndNotify(
        Student $student,
        Instructor $sourceInstructor,
        Instructor $destination,
        User $admin,
        TransferReason $reason,
        ?string $notes,
        Collection $movedLessons,
        Collection $clashingLessons,
        Collection $unscheduledClashes,
        Collection $missedLessons,
        array $missedDescriptions,
        Collection $cancelledOrders,
    ): void {
        $student->refresh();
        $unscheduled = ($this->getUnscheduledLessonsSummary)($student);

        $metadata = [
            'from_instructor_id' => $sourceInstructor->id,
            'to_instructor_id' => $destination->id,
            'transferred_by_user_id' => $admin->id,
            'transferred_by_name' => $admin->name,
            'reason' => $reason->value,
            'reason_label' => $reason->label(),
            'notes' => $notes,
            'affected_lesson_ids' => $movedLessons->pluck('id')->all(),
            'clashing_lesson_ids' => $clashingLessons->pluck('id')->all(),
            'unscheduled_lesson_ids' => $unscheduledClashes->pluck('id')->all(),
            'missed_lesson_ids' => $missedLessons->pluck('id')->all(),
            'cancelled_order_ids' => $cancelledOrders->pluck('id')->all(),
        ];

        $studentDisplayName = trim("{$student->first_name} {$student->surname}") ?: ($student->email ?? "Student #{$student->id}");
        $sourceName = $sourceInstructor->name ?? "Instructor #{$sourceInstructor->id}";
        $destinationName = $destination->name ?? "Instructor #{$destination->id}";
        $actionedBy = $admin->name ?? "User #{$admin->id}";
        $auditSuffix = " by {$actionedBy}. Reason: {$reason->label()}".($notes !== null && $notes !== '' ? " — {$notes}" : '');

        $student->logActivity("Transferred from {$sourceName} to {$destinationName}{$auditSuffix}", 'instructor_transfer', $metadata);
        $sourceInstructor->logActivity("Student {$studentDisplayName} transferred to {$destinationName}{$auditSuffix}", 'student_lost', $metadata);
        $destination->logActivity("Student {$studentDisplayName} transferred from {$sourceName}{$auditSuffix}", 'student_gained', $metadata);

        $student->user?->notify(new StudentTransferToStudentNotification(
            $student,
            $destination,
            $movedLessons->count(),
            $unscheduled['count'],
            $cancelledOrders->count(),
        ));

        $sourceInstructor->user?->notify(new StudentLostNotification(
            $student,
            $sourceInstructor,
            $destination,
            $movedLessons->count() + $unscheduledClashes->count(),
            $missedDescriptions,
        ));

        $destination->user?->notify(new StudentGainedNotification(
            $student,
            $sourceInstructor,
            $destination,
            new EloquentCollection($movedLessons->all()),
            new EloquentCollection($clashingLessons->all()),
            $unscheduled['count'],
            $unscheduled['paid_count'],
        ));
    }

    /**
     * "Sat 21 Nov 2026, 08:00–10:00".
     */
    protected function describe(Lesson $lesson): string
    {
        $when = $lesson->date?->format('D j M Y') ?? 'an unknown date';

        return $lesson->start_time && $lesson->end_time
            ? $when.', '.$lesson->start_time->format('H:i').'–'.$lesson->end_time->format('H:i')
            : $when;
    }
}
