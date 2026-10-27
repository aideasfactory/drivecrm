<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Actions\Lesson\GetBankedLessonsAction;
use App\Models\Lesson;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetBankedLessonsReportAction
{
    public function __construct(
        protected GetBankedLessonsAction $getBankedLessons,
    ) {}

    /**
     * Every lesson kept in its booking to rebook later, longest waiting
     * first, for Head Office to chase the instructor to rebook before they
     * expire (`lessons:expire-banked` cancels and refunds them).
     *
     * @return array{rows: Collection<int, array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, learner_phone: ?string, learner_email: ?string, instructor_name: ?string, package: ?string, order_id: ?int, amount_pence: int, payment_status: ?string, banked_at: ?string, days_waiting: int, expires_at: ?string, banked_by: ?string, reason: ?string}>, generated_at: string}
     */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();

        $rows = ($this->getBankedLessons)()
            ->map(fn (Lesson $lesson): array => $this->row($lesson, $now))
            ->values();

        return [
            'rows' => $rows,
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @return array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, learner_phone: ?string, learner_email: ?string, instructor_name: ?string, package: ?string, order_id: ?int, amount_pence: int, payment_status: ?string, banked_at: ?string, days_waiting: int, expires_at: ?string, banked_by: ?string, reason: ?string}
     */
    protected function row(Lesson $lesson, CarbonImmutable $now): array
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
            'package' => $order?->package_name,
            'order_id' => $order?->id,
            'amount_pence' => (int) $lesson->amount_pence,
            'payment_status' => $lesson->lessonPayment?->status?->value ?? ($order?->isPrepaid() ? 'paid' : null),
            'banked_at' => $lesson->banked_at?->toIso8601String(),
            'days_waiting' => (int) $lesson->banked_at?->diffInDays($now),
            'expires_at' => $lesson->bankedExpiresAt()?->toIso8601String(),
            'banked_by' => $lesson->bankedBy?->name,
            'reason' => $lesson->banked_reason,
        ];
    }

    protected function studentName(?Student $student): ?string
    {
        $name = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        return $name !== '' ? $name : null;
    }
}
