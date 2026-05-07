<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StudentTransferRequest;
use App\Models\Instructor;
use App\Models\Student;
use App\Services\StudentTransferService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentTransferController extends Controller
{
    public function __construct(
        protected StudentTransferService $studentTransferService,
    ) {}

    public function index(): Response
    {
        $students = $this->studentTransferService->getTransferableStudents()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => trim("{$student->first_name} {$student->surname}"),
                'email' => $student->email,
                'current_instructor_id' => $student->instructor_id,
                'current_instructor_name' => $student->instructor?->name,
            ])
            ->values();

        $instructors = $this->studentTransferService->getOnboardedInstructors()
            ->map(fn (Instructor $instructor) => [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'email' => $instructor->user?->email,
            ])
            ->values();

        return Inertia::render('StudentTransfers/Index', [
            'students' => $students,
            'instructors' => $instructors,
        ]);
    }

    public function store(StudentTransferRequest $request): RedirectResponse
    {
        $student = Student::findOrFail($request->validated('student_id'));
        $destination = Instructor::findOrFail($request->validated('destination_instructor_id'));

        $result = $this->studentTransferService->transferStudent(
            $student,
            $destination,
            $request->user(),
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
