<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransferReason;
use App\Http\Requests\StudentTransferRequest;
use App\Http\Requests\StudentTransferSearchRequest;
use App\Models\Instructor;
use App\Models\Student;
use App\Services\StudentTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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

    public function store(StudentTransferRequest $request): RedirectResponse
    {
        $student = Student::findOrFail($request->validated('student_id'));
        $destination = Instructor::findOrFail($request->validated('destination_instructor_id'));

        $result = $this->studentTransferService->transferStudent(
            $student,
            $destination,
            $request->user(),
            TransferReason::from($request->validated('reason')),
            $request->validated('notes'),
        );

        $studentName = trim("{$student->first_name} {$student->surname}") ?: ($student->email ?? "Student #{$student->id}");
        $movedCount = $result['moved_lessons']->count();
        $clashCount = $result['clashing_lessons']->count();

        $message = "{$studentName} transferred to {$destination->name}. {$movedCount} ".($movedCount === 1 ? 'lesson moved' : 'lessons moved').'.';

        if ($clashCount > 0) {
            $message .= " {$clashCount} ".($clashCount === 1 ? 'clash' : 'clashes').' flagged in the new instructor’s email.';
        }

        return redirect()->route('student-transfers.index')->with('success', $message);
    }
}
