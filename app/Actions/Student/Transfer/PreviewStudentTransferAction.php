<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Collection;
use RuntimeException;

class PreviewStudentTransferAction
{
    /**
     * Everything a pupil has that a move to `$destination` affects, sorted
     * into what will happen to it. Changes nothing.
     *
     * - `past_unsigned`: lessons with the current instructor that have started
     *   but were never signed off. Head Office must say whether each happened.
     *   Driving tests are left out: they stay with the current instructor.
     * - `future_free`: future lessons whose time is free in the new diary; they move as they are.
     * - `future_clashes`: future lessons whose time is taken in the new diary,
     *   with what they clash with. They become unscheduled with the new
     *   instructor, unless they can't be (a driving test), in which case they
     *   move anyway and are flagged.
     * - `unscheduled`: lessons already waiting to be scheduled; they follow the pupil.
     * - `pending_orders`: bookings still waiting for their first payment; they are cancelled.
     *
     * @return array{
     *     source: Instructor,
     *     destination: Instructor,
     *     past_unsigned: Collection<int, Lesson>,
     *     future_free: Collection<int, Lesson>,
     *     future_clashes: Collection<int, array{lesson: Lesson, clashes_with: string, can_unschedule: bool}>,
     *     unscheduled: Collection<int, Lesson>,
     *     pending_orders: Collection<int, Order>,
     * }
     *
     * @throws RuntimeException When the pupil has no instructor
     */
    public function __invoke(Student $student, Instructor $destination): array
    {
        $source = $student->instructor()->with('user')->first();

        if (! $source) {
            throw new RuntimeException('Cannot transfer a student who has no current instructor.');
        }

        $pendingOrders = Order::query()
            ->where('student_id', $student->id)
            ->where('status', OrderStatus::PENDING)
            ->where('payment_mode', '!=', PaymentMode::IMPORTED)
            ->withCount('lessons')
            ->orderBy('created_at')
            ->get();

        $openLessons = Lesson::query()
            ->where('instructor_id', $source->id)
            ->whereHas('order', fn ($query) => $query
                ->where('student_id', $student->id)
                ->whereNotIn('id', $pendingOrders->pluck('id'))
                ->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::EXPIRED]))
            ->where('status', LessonStatus::PENDING)
            ->whereNull('banked_at')
            ->whereNotNull('date')
            ->whereDoesntHave('payout')
            ->with(['order', 'lessonPayment', 'calendarItem', 'payout'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        [$started, $future] = $openLessons->partition(fn (Lesson $lesson): bool => $lesson->hasStarted());

        $futureWithClashes = $future->values()->map(fn (Lesson $lesson): array => [
            'lesson' => $lesson,
            'clashes_with' => $this->clashIn($destination, $lesson),
        ]);

        return [
            'source' => $source,
            'destination' => $destination,
            'past_unsigned' => $started->reject(fn (Lesson $lesson): bool => (bool) $lesson->is_practical_test)->values(),
            'future_free' => $futureWithClashes->whereNull('clashes_with')->pluck('lesson')->values(),
            'future_clashes' => $futureWithClashes->whereNotNull('clashes_with')
                ->map(fn (array $entry): array => [
                    ...$entry,
                    'can_unschedule' => $entry['lesson']->canBeBanked(),
                ])
                ->values(),
            'unscheduled' => Lesson::query()
                ->banked()
                ->where('instructor_id', $source->id)
                ->whereHas('order', fn ($query) => $query->where('student_id', $student->id))
                ->with(['order', 'lessonPayment'])
                ->orderBy('banked_at')
                ->get(),
            'pending_orders' => $pendingOrders,
        ];
    }

    /**
     * What already takes the lesson's time in the destination diary (another
     * lesson or hold, blocked-out time or a driving test), or null when free.
     */
    protected function clashIn(Instructor $destination, Lesson $lesson): ?string
    {
        if (! $lesson->start_time || ! $lesson->end_time) {
            return null;
        }

        $clash = CalendarItem::query()
            ->whereHas('calendar', fn ($query) => $query
                ->where('instructor_id', $destination->id)
                ->whereDate('date', $lesson->date->toDateString()))
            ->overlapping($lesson->start_time->format('H:i:s'), $lesson->end_time->format('H:i:s'))
            ->blocksBooking()
            ->with(['lessons.order.student', 'student'])
            ->orderBy('start_time')
            ->first();

        if (! $clash) {
            return null;
        }

        if ($clash->item_type === CalendarItemType::PracticalTest) {
            return 'a driving test';
        }

        $otherStudent = $clash->lessons->first()?->order?->student;

        if ($otherStudent) {
            return trim("{$otherStudent->first_name} {$otherStudent->surname}")."'s lesson";
        }

        return $clash->status === null ? 'blocked-out time' : 'another booking';
    }
}
