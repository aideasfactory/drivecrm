<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Actions\Calendar\DescribeBlockingCalendarItemAction;
use App\Actions\Calendar\FindBlockingCalendarItemsAction;
use App\Enums\LessonStatus;
use App\Enums\OrderSource;
use App\Enums\ProvisionalLessonHandler;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetClashingLessonsAction
{
    public function __construct(
        protected FindBlockingCalendarItemsAction $findBlockingCalendarItems,
        protected DescribeBlockingCalendarItemAction $describeBlockingCalendarItem,
    ) {}

    /**
     * Get every open provisional lesson: a later week of a package that was
     * booked onto a time already taken in the instructor's diary. Only the
     * later booking is listed, never what it clashes with. Soonest first.
     *
     * @return array{rows: Collection<int, array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, learner_phone: ?string, learner_email: ?string, instructor_name: ?string, lesson_date: string, lesson_time: string, package: ?string, booked_at: ?string, booked_by: string, handled_by: string, awaiting_payment: bool, clashes_with: array<int, string>}>, generated_at: string}
     */
    public function __invoke(): array
    {
        $rows = Lesson::query()
            ->provisional()
            ->with(['order.student', 'order.bookedBy', 'instructor.user', 'calendarItem'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Lesson $lesson): array => $this->row($lesson))
            ->values();

        return [
            'rows' => $rows,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @return array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, learner_phone: ?string, learner_email: ?string, instructor_name: ?string, lesson_date: string, lesson_time: string, package: ?string, booked_at: ?string, booked_by: string, handled_by: string, awaiting_payment: bool, clashes_with: array<int, string>}
     */
    protected function row(Lesson $lesson): array
    {
        $order = $lesson->order;
        $student = $order?->student;
        $contact = $student?->getBookerDetails() ?? [];

        return [
            'lesson_id' => $lesson->id,
            'student_id' => $student?->id,
            // Profile link target: the student's assigned instructor page,
            // falling back to the lesson's instructor
            'instructor_id' => $student?->instructor_id ?? $lesson->instructor_id,
            'learner_name' => $this->studentName($student) ?? 'Unknown learner',
            'learner_phone' => $contact['phone'] ?? $student?->phone,
            'learner_email' => $contact['email'] ?? $student?->email,
            'instructor_name' => $lesson->instructor?->name,
            'lesson_date' => $lesson->date->toDateString(),
            'lesson_time' => $lesson->start_time->format('H:i').' – '.$lesson->end_time->format('H:i'),
            'package' => $order?->package_name,
            'booked_at' => $order?->created_at?->toIso8601String(),
            'booked_by' => $order?->bookedBy?->name ?? $order?->source?->label() ?? OrderSource::unknownLabel(),
            // Instructor when they booked the clash themselves (they rearrange it)
            'handled_by' => ($lesson->provisionalHandledBy() ?? ProvisionalLessonHandler::DRIVE_TEAM)->label(),
            'awaiting_payment' => $lesson->status === LessonStatus::DRAFT,
            // Empty when whatever it clashed with has since moved or gone —
            // the team can then mark it confirmed.
            'clashes_with' => $lesson->calendarItem
                ? ($this->findBlockingCalendarItems)($lesson->calendarItem)
                    ->map(fn (CalendarItem $item): string => ($this->describeBlockingCalendarItem)($item))
                    ->values()
                    ->all()
                : [],
        ];
    }

    protected function studentName(?Student $student): ?string
    {
        $name = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        return $name !== '' ? $name : null;
    }
}
