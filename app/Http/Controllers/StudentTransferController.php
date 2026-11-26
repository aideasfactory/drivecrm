<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransferReason;
use App\Http\Requests\StudentTransferPreviewRequest;
use App\Http\Requests\StudentTransferRequest;
use App\Http\Requests\StudentTransferSearchRequest;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Services\StudentTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class StudentTransferController extends Controller
{
    protected const SEARCH_RESULT_LIMIT = 25;

    public function __construct(
        protected StudentTransferService $studentTransferService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('StudentTransfers/Index', [
            'hasStudents' => $this->studentTransferService->getTransferableStudents(limit: 1)->isNotEmpty(),
            'hasInstructors' => $this->studentTransferService->getOnboardedInstructors(limit: 1)->isNotEmpty(),
            'reasons' => TransferReason::options(),
        ]);
    }

    public function searchStudents(StudentTransferSearchRequest $request): JsonResponse
    {
        $students = $this->studentTransferService
            ->getTransferableStudents($request->validated('q'), self::SEARCH_RESULT_LIMIT)
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => trim("{$student->first_name} {$student->surname}"),
                'email' => $student->email,
                'phone' => $student->phone,
                'current_instructor_id' => $student->instructor_id,
                'current_instructor_name' => $student->instructor?->name,
            ])
            ->values();

        return response()->json(['students' => $students]);
    }

    public function searchInstructors(StudentTransferSearchRequest $request): JsonResponse
    {
        $instructors = $this->studentTransferService
            ->getOnboardedInstructors($request->validated('q'), self::SEARCH_RESULT_LIMIT)
            ->map(fn (Instructor $instructor) => [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'email' => $instructor->user?->email,
                'phone' => $instructor->phone,
            ])
            ->values();

        return response()->json(['instructors' => $instructors]);
    }

    /**
     * The Pupil Transfer Check: what moving the pupil would do. Changes nothing.
     */
    public function preview(StudentTransferPreviewRequest $request): JsonResponse
    {
        $student = Student::findOrFail($request->validated('student_id'));
        $destination = Instructor::findOrFail($request->validated('destination_instructor_id'));

        try {
            $preview = $this->studentTransferService->previewTransfer($student, $destination);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => trim("{$student->first_name} {$student->surname}"),
            ],
            'source_instructor' => ['id' => $preview['source']->id, 'name' => $preview['source']->name],
            'destination_instructor' => ['id' => $destination->id, 'name' => $destination->name],
            'past_unsigned' => $preview['past_unsigned']->map(fn (Lesson $lesson): array => $this->lessonRow($lesson))->values(),
            'future' => $preview['future_free']
                ->map(fn (Lesson $lesson): array => [...$this->lessonRow($lesson), 'clashes_with' => null, 'can_unschedule' => true])
                ->concat($preview['future_clashes']->map(fn (array $clash): array => [
                    ...$this->lessonRow($clash['lesson']),
                    'clashes_with' => $clash['clashes_with'],
                    'can_unschedule' => $clash['can_unschedule'],
                ]))
                ->sortBy(fn (array $row): string => $row['date'].' '.$row['start_time'])
                ->values(),
            'unscheduled' => $preview['unscheduled']->map(fn (Lesson $lesson): array => $this->lessonRow($lesson))->values(),
            'pending_orders' => $preview['pending_orders']->map(fn (Order $order): array => [
                'id' => $order->id,
                'package_name' => $order->package_name,
                'lessons_count' => $order->lessons_count,
                'payment_mode' => $order->payment_mode?->value,
            ])->values(),
        ]);
    }

    public function store(StudentTransferRequest $request): RedirectResponse
    {
        $student = Student::findOrFail($request->validated('student_id'));
        $destination = Instructor::findOrFail($request->validated('destination_instructor_id'));

        try {
            $result = $this->studentTransferService->transferStudent(
                $student,
                $destination,
                $request->user(),
                TransferReason::from($request->validated('reason')),
                $request->validated('notes'),
                $request->lessonDecisions(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        $studentName = trim("{$student->first_name} {$student->surname}") ?: ($student->email ?? "Student #{$student->id}");
        $movedCount = $result['moved_lessons']->count();
        $unscheduledCount = $result['unscheduled_lessons']->count() + $result['missed_lessons']->count();
        $clashCount = $result['clashing_lessons']->count();
        $cancelledCount = $result['cancelled_orders']->count();

        $message = "{$studentName} transferred to {$destination->name}. {$movedCount} ".($movedCount === 1 ? 'lesson' : 'lessons').' moved into the diary';
        $message .= $unscheduledCount > 0 ? ", {$unscheduledCount} made unscheduled." : '.';

        if ($cancelledCount > 0) {
            $message .= " {$cancelledCount} unpaid ".($cancelledCount === 1 ? 'booking' : 'bookings').' cancelled.';
        }

        if ($clashCount > 0) {
            $message .= " {$clashCount} ".($clashCount === 1 ? 'clash' : 'clashes').' flagged in the new instructor’s email.';
        }

        foreach ($result['problems'] as $problem) {
            $message .= " ⚠️ {$problem}";
        }

        return redirect()->route('student-transfers.index')->with('success', $message);
    }

    /**
     * @return array{id: int, date: ?string, start_time: ?string, end_time: ?string, package_name: ?string, payment_status: string, is_practical_test: bool}
     */
    private function lessonRow(Lesson $lesson): array
    {
        $isPaid = $lesson->isPaid() || ($lesson->order?->isPrepaid() === true && ! $lesson->order->isCash());

        return [
            'id' => $lesson->id,
            'date' => $lesson->date?->toDateString(),
            'start_time' => $lesson->start_time?->format('H:i'),
            'end_time' => $lesson->end_time?->format('H:i'),
            'package_name' => $lesson->order?->package_name,
            'payment_status' => $isPaid ? 'paid' : ($lesson->order?->isCash() ? 'cash' : 'due'),
            'is_practical_test' => (bool) $lesson->is_practical_test,
        ];
    }
}
