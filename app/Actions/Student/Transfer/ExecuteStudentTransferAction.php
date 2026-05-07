<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentTransfer\StudentGainedNotification;
use App\Notifications\StudentTransfer\StudentLostNotification;
use App\Notifications\StudentTransfer\StudentTransferToStudentNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteStudentTransferAction
{
    public function __construct(
        protected DetectLessonClashesAction $detectClashes,
    ) {}

    /**
     * Move a student to a new instructor. Updates `students.instructor_id`,
     * reassigns all future un-paid-out lessons to the new instructor, writes
     * audit-trail activity logs against all three parties, and dispatches
     * notification emails. The new instructor's email lists any clashes with
     * their existing diary so they can rebook.
     *
     * @return array{
     *     student: Student,
     *     source_instructor: Instructor,
     *     destination_instructor: Instructor,
     *     moved_lessons: Collection<int, Lesson>,
     *     clashing_lessons: Collection<int, Lesson>,
     * }
     */
    public function __invoke(Student $student, Instructor $destination, User $admin): array
    {
        $sourceInstructor = $student->instructor()->with('user')->first();

        if (! $sourceInstructor) {
            throw new RuntimeException('Cannot transfer a student who has no current instructor.');
        }

        if ($sourceInstructor->id === $destination->id) {
            throw new RuntimeException('The destination instructor is the same as the current instructor.');
        }

        $destination->loadMissing('user');

        $futureLessons = Lesson::query()
            ->where('instructor_id', $sourceInstructor->id)
            ->whereHas('order', fn ($q) => $q->where('student_id', $student->id))
            ->where('date', '>=', today())
            ->whereDoesntHave('payout')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $clashingLessons = ($this->detectClashes)($destination, $futureLessons);

        DB::transaction(function () use ($student, $destination, $futureLessons): void {
            foreach ($futureLessons as $lesson) {
                if (! $lesson->calendar_item_id) {
                    continue;
                }

                $destinationCalendar = Calendar::firstOrCreate([
                    'instructor_id' => $destination->id,
                    'date' => $lesson->date->toDateString(),
                ]);

                $calendarItem = CalendarItem::with('travelItem')->find($lesson->calendar_item_id);

                if (! $calendarItem) {
                    continue;
                }

                $calendarItem->update(['calendar_id' => $destinationCalendar->id]);
                $calendarItem->travelItem?->update(['calendar_id' => $destinationCalendar->id]);
            }

            if ($futureLessons->isNotEmpty()) {
                Lesson::query()
                    ->whereIn('id', $futureLessons->pluck('id'))
                    ->update(['instructor_id' => $destination->id]);
            }

            $student->update(['instructor_id' => $destination->id]);
        });

        $metadata = [
            'from_instructor_id' => $sourceInstructor->id,
            'to_instructor_id' => $destination->id,
            'transferred_by_user_id' => $admin->id,
            'affected_lesson_ids' => $futureLessons->pluck('id')->all(),
            'clashing_lesson_ids' => $clashingLessons->pluck('id')->all(),
        ];

        $studentDisplayName = trim("{$student->first_name} {$student->surname}") ?: ($student->email ?? "Student #{$student->id}");
        $sourceName = $sourceInstructor->name ?? "Instructor #{$sourceInstructor->id}";
        $destinationName = $destination->name ?? "Instructor #{$destination->id}";

        $student->logActivity(
            "Transferred from {$sourceName} to {$destinationName}",
            'instructor_transfer',
            $metadata,
        );

        $sourceInstructor->logActivity(
            "Student {$studentDisplayName} transferred to {$destinationName}",
            'student_lost',
            $metadata,
        );

        $destination->logActivity(
            "Student {$studentDisplayName} transferred from {$sourceName}",
            'student_gained',
            $metadata,
        );

        if ($student->user) {
            $student->user->notify(new StudentTransferToStudentNotification($student, $destination));
        }

        if ($sourceInstructor->user) {
            $sourceInstructor->user->notify(new StudentLostNotification($student, $sourceInstructor, $destination, $futureLessons->count()));
        }

        if ($destination->user) {
            $destination->user->notify(new StudentGainedNotification($student, $sourceInstructor, $destination, $futureLessons, $clashingLessons));
        }

        return [
            'student' => $student->fresh(['instructor.user']),
            'source_instructor' => $sourceInstructor,
            'destination_instructor' => $destination,
            'moved_lessons' => $futureLessons,
            'clashing_lessons' => $clashingLessons,
        ];
    }
}
