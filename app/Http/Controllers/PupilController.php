<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shared\Contact\CreateContactAction;
use App\Actions\Shared\Contact\DeleteContactAction;
use App\Actions\Shared\Contact\SetPrimaryContactAction;
use App\Actions\Shared\Contact\UpdateContactAction;
use App\Actions\Shared\Message\GetConversationAction;
use App\Actions\Shared\Message\SendMessageAction;
use App\Actions\Shared\Note\CreateNoteAction;
use App\Actions\Shared\Note\DeleteNoteAction;
use App\Actions\Shared\Note\GetNotesAction;
use App\Actions\Student\GetStudentDetailAction;
use App\Jobs\ProcessLessonSignOffJob;
use App\Models\Contact;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\Student;
use App\Services\LessonSignOffService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PupilController extends Controller
{
    /**
     * Display the pupils index page.
     */
    public function index(): Response
    {
        return Inertia::render('Pupils/Index');
    }

    /**
     * Get student detail data for the pupil detail page header.
     */
    public function show(Student $student): JsonResponse
    {
        $data = (new GetStudentDetailAction)($student);

        return response()->json([
            'student' => $data,
        ]);
    }

    /**
     * Get emergency contacts for a student.
     */
    public function contacts(Student $student): JsonResponse
    {
        $contacts = $student->contacts()->orderByDesc('is_primary')->orderBy('name')->get();

        return response()->json([
            'contacts' => $contacts,
        ]);
    }

    /**
     * Store a new emergency contact for a student.
     */
    public function storeContact(Student $student): JsonResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        $contact = (new CreateContactAction)($student, $data);

        return response()->json([
            'contact' => $contact,
        ], 201);
    }

    /**
     * Update an emergency contact for a student.
     */
    public function updateContact(Student $student, Contact $contact): JsonResponse
    {
        if ($contact->contactable_id !== $student->id || $contact->contactable_type !== Student::class) {
            return response()->json(['message' => 'Contact not found for this student.'], 404);
        }

        $data = request()->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        $contact = (new UpdateContactAction)($contact, $data);

        return response()->json([
            'contact' => $contact,
        ]);
    }

    /**
     * Delete an emergency contact for a student.
     */
    public function deleteContact(Student $student, Contact $contact): JsonResponse
    {
        if ($contact->contactable_id !== $student->id || $contact->contactable_type !== Student::class) {
            return response()->json(['message' => 'Contact not found for this student.'], 404);
        }

        (new DeleteContactAction)($contact);

        return response()->json([
            'message' => 'Contact deleted successfully.',
        ]);
    }

    /**
     * Set an emergency contact as primary for a student.
     */
    public function setPrimaryContact(Student $student, Contact $contact): JsonResponse
    {
        if ($contact->contactable_id !== $student->id || $contact->contactable_type !== Student::class) {
            return response()->json(['message' => 'Contact not found for this student.'], 404);
        }

        $contact = (new SetPrimaryContactAction)($contact);

        return response()->json([
            'contact' => $contact,
        ]);
    }

    /**
     * Get notes for a student.
     */
    public function notes(Student $student): JsonResponse
    {
        $notes = (new GetNotesAction)($student);

        return response()->json([
            'notes' => $notes->items(),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'total' => $notes->total(),
                'per_page' => $notes->perPage(),
                'last_page' => $notes->lastPage(),
            ],
        ]);
    }

    /**
     * Store a new note for a student.
     */
    public function storeNote(Student $student): JsonResponse
    {
        $data = request()->validate([
            'note' => 'required|string|max:5000',
        ]);

        $note = app(CreateNoteAction::class)($student, $data['note']);

        return response()->json([
            'note' => $note,
        ], 201);
    }

    /**
     * Delete a note for a student.
     */
    public function deleteNote(Student $student, Note $note): JsonResponse
    {
        if ($note->noteable_id !== $student->id || $note->noteable_type !== Student::class) {
            return response()->json(['message' => 'Note not found for this student.'], 404);
        }

        (new DeleteNoteAction)($note);

        return response()->json([
            'message' => 'Note deleted successfully.',
        ]);
    }

    /**
     * Get conversation messages between student and their instructor.
     */
    public function messages(Student $student): JsonResponse
    {
        if (! $student->user_id) {
            return response()->json(['message' => 'Student does not have a user account.'], 422);
        }

        if (! $student->instructor_id) {
            return response()->json(['message' => 'Student does not have an assigned instructor.'], 422);
        }

        $instructor = $student->instructor;
        $studentUser = $student->user;
        $instructorUser = $instructor->user;

        $messages = (new GetConversationAction)($instructorUser, $studentUser);

        return response()->json([
            'messages' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'last_page' => $messages->lastPage(),
            ],
            'context' => [
                'instructor_user_id' => $instructorUser->id,
                'instructor_name' => $instructorUser->name,
                'student_user_id' => $studentUser->id,
                'student_name' => $student->first_name.' '.$student->surname,
            ],
        ]);
    }

    /**
     * Send a message from the instructor to the student.
     */
    public function sendMessage(Student $student): JsonResponse
    {
        if (! $student->user_id) {
            return response()->json(['message' => 'Student does not have a user account.'], 422);
        }

        if (! $student->instructor_id) {
            return response()->json(['message' => 'Student does not have an assigned instructor.'], 422);
        }

        $data = request()->validate([
            'message' => 'required|string|max:5000',
        ]);

        $instructor = $student->instructor;

        $message = app(SendMessageAction::class)(
            $instructor->user,
            $student->user,
            $data['message'],
            $student,
            $instructor
        );

        return response()->json([
            'message' => $message->load('sender:id,name'),
        ], 201);
    }

    /**
     * Get all lessons for a student.
     */
    public function lessons(Student $student, LessonSignOffService $service): JsonResponse
    {
        $lessons = $service->getStudentLessons($student);

        return response()->json([
            'lessons' => $lessons,
        ]);
    }

    /**
     * Sign off a lesson as completed, triggering payout and notifications.
     */
    public function signOffLesson(Student $student, Lesson $lesson): JsonResponse
    {
        // Verify lesson belongs to this student (via order)
        $lessonBelongsToStudent = $student->orders()
            ->whereHas('lessons', fn ($q) => $q->where('id', $lesson->id))
            ->exists();

        if (! $lessonBelongsToStudent) {
            return response()->json(['message' => 'Lesson not found for this student.'], 404);
        }

        // Lesson must be pending
        if ($lesson->isCompleted()) {
            return response()->json(['message' => 'This lesson has already been completed.'], 422);
        }

        $instructor = $lesson->instructor;

        if (! $instructor) {
            return response()->json(['message' => 'No instructor assigned to this lesson.'], 422);
        }

        // Dispatch sign-off job for async processing
        ProcessLessonSignOffJob::dispatch($lesson, $instructor);

        return response()->json([
            'message' => 'Lesson sign-off is being processed.',
        ]);
    }

    /**
     * Get activity logs for a student.
     */
    public function activityLogs(Student $student): JsonResponse
    {
        $query = $student->activityLogs()->recent();

        // Filter by category
        if (request()->has('category') && request('category') !== 'all') {
            $query->category(request('category'));
        }

        // Search by message
        if (request()->has('search') && request('search')) {
            $query->where('message', 'like', '%'.request('search').'%');
        }

        // Paginate
        $logs = $query->paginate(20);

        return response()->json([
            'logs' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
