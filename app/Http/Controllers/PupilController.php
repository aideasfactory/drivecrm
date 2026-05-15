<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payment\ResendLessonInvoiceAction;
use App\Actions\Shared\AdminResetPasswordAction;
use App\Actions\Shared\Contact\CreateContactAction;
use App\Actions\Shared\Contact\DeleteContactAction;
use App\Actions\Shared\Contact\SetPrimaryContactAction;
use App\Actions\Shared\Contact\UpdateContactAction;
use App\Actions\Shared\Message\GetConversationAction;
use App\Actions\Shared\Message\SendMessageAction;
use App\Actions\Shared\Note\CreateNoteAction;
use App\Actions\Shared\Note\DeleteNoteAction;
use App\Actions\Shared\Note\GetNotesAction;
use App\Actions\Student\Checklist\GetStudentChecklistAction;
use App\Actions\Student\Checklist\ToggleChecklistItemAction;
use App\Actions\Student\Contact\AutoCreateEmergencyContactAction;
use App\Actions\Student\GetStudentDetailAction;
use App\Actions\Student\Payment\GetStudentPaymentsAction;
use App\Actions\Student\PickupPoint\CreatePickupPointAction;
use App\Actions\Student\PickupPoint\DeletePickupPointAction;
use App\Actions\Student\PickupPoint\GetStudentPickupPointsAction;
use App\Actions\Student\PickupPoint\SetDefaultPickupPointAction;
use App\Actions\Student\PickupPoint\UpdatePickupPointAction;
use App\Actions\Student\Status\RemoveStudentFromInstructorAction;
use App\Actions\Student\Status\UpdateStudentStatusAction;
use App\Enums\PaymentMode;
use App\Http\Requests\AdminResetPasswordRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\StorePickupPointRequest;
use App\Http\Requests\UpdatePickupPointRequest;
use App\Http\Requests\UpdateStudentStatusRequest;
use App\Jobs\ProcessLessonSignOffJob;
use App\Models\Contact;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\Package;
use App\Models\Student;
use App\Models\StudentChecklistItem;
use App\Models\StudentPickupPoint;
use App\Services\InstructorCalendarService;
use App\Services\LessonSignOffService;
use App\Services\OrderService;
use App\Services\StudentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PupilController extends Controller
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * Display the pupils index page.
     */
    public function index(): Response
    {
        $students = $this->studentService->getAll();

        return Inertia::render('Pupils/Index', [
            'pupils' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->surname),
                'email' => $student->email,
                'status' => $student->status,
                'instructor_id' => $student->instructor_id,
                'instructor_name' => $student->instructor?->user?->name,
            ]),
        ]);
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
            'address' => 'nullable|string|max:1000',
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
            'address' => 'nullable|string|max:1000',
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
     * Auto-create an emergency contact from the student's third-party contact details.
     */
    public function autoCreateEmergencyContact(Student $student): JsonResponse
    {
        $contact = app(AutoCreateEmergencyContactAction::class)($student);

        if (! $contact) {
            return response()->json([
                'message' => 'No contact details available to auto-create, or contacts already exist.',
                'created' => false,
            ]);
        }

        return response()->json([
            'contact' => $contact,
            'created' => true,
        ], 201);
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
     * Get payment records for a student.
     */
    public function payments(Student $student): JsonResponse
    {
        $payments = app(GetStudentPaymentsAction::class)($student);

        return response()->json([
            'payments' => $payments,
        ]);
    }

    /**
     * Get available calendar slots for the student's instructor on a given date.
     */
    public function availableSlots(Student $student, InstructorCalendarService $calendarService): JsonResponse
    {
        $date = request()->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d'],
        ])['date'];

        if (! $student->instructor_id) {
            return response()->json(['slots' => []], 200);
        }

        $instructor = Instructor::find($student->instructor_id);

        if (! $instructor) {
            return response()->json(['slots' => []], 200);
        }

        $items = $calendarService->getCalendarItems($instructor, $date, availableOnly: true, excludeDrafts: true);

        return response()->json([
            'slots' => $items->map(fn ($item) => [
                'id' => $item->id,
                'start_time' => Carbon::parse($item->start_time)->format('H:i'),
                'end_time' => Carbon::parse($item->end_time)->format('H:i'),
            ])->values(),
        ]);
    }

    /**
     * Book lessons on behalf of a student (admin version of the mobile API flow).
     */
    public function storeOrder(StoreOrderRequest $request, Student $student, OrderService $orderService): JsonResponse
    {
        $validated = $request->validated();

        $package = Package::where('active', true)->findOrFail($validated['package_id']);

        if (! $student->instructor_id) {
            return response()->json([
                'message' => 'Student must have an assigned instructor to book lessons.',
            ], 422);
        }

        $paymentMode = PaymentMode::from($validated['payment_mode']);

        $result = $orderService->bookLessons(
            $student,
            $package,
            $paymentMode,
            $validated['first_lesson_date'],
            $validated['start_time'],
            $validated['end_time']
        );

        $message = $paymentMode === PaymentMode::WEEKLY
            ? 'Order created and activated. Lesson invoices will be sent before each lesson.'
            : 'Order created. A payment link has been emailed to the student.';

        return response()->json([
            'message' => $message,
            'order' => $result['order'],
        ], 201);
    }

    /**
     * Sign off a lesson as completed, triggering payout and notifications.
     */
    public function signOffLesson(Student $student, Lesson $lesson): JsonResponse
    {
        $data = request()->validate([
            'summary' => 'required|string|max:5000',
        ]);

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
        ProcessLessonSignOffJob::dispatch($lesson, $instructor, $data['summary']);

        return response()->json([
            'message' => 'Lesson sign-off is being processed.',
        ]);
    }

    /**
     * Resend the invoice email for a lesson payment.
     */
    public function resendLessonInvoice(Student $student, Lesson $lesson, ResendLessonInvoiceAction $resendInvoice): JsonResponse
    {
        $lessonBelongsToStudent = $student->orders()
            ->whereHas('lessons', fn ($q) => $q->where('id', $lesson->id))
            ->exists();

        if (! $lessonBelongsToStudent) {
            return response()->json(['message' => 'Lesson not found for this student.'], 404);
        }

        $lessonPayment = $lesson->lessonPayment;

        if (! $lessonPayment || ! $lessonPayment->stripe_invoice_id) {
            return response()->json(['message' => 'No invoice exists for this lesson.'], 422);
        }

        if ($lessonPayment->isPaid()) {
            return response()->json(['message' => 'This lesson has already been paid.'], 422);
        }

        $result = $resendInvoice($lessonPayment);

        if (! $result['success']) {
            return response()->json(['message' => $result['error'] ?? 'Failed to resend invoice.'], 500);
        }

        return response()->json(['message' => 'Invoice email has been resent.']);
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

    /**
     * Get pickup points for a student.
     */
    public function pickupPoints(Student $student): JsonResponse
    {
        $pickupPoints = app(GetStudentPickupPointsAction::class)($student);

        return response()->json([
            'pickup_points' => $pickupPoints,
        ]);
    }

    /**
     * Store a new pickup point for a student.
     */
    public function storePickupPoint(Student $student, StorePickupPointRequest $request): JsonResponse
    {
        $pickupPoint = app(CreatePickupPointAction::class)($student, $request->validated());

        return response()->json([
            'pickup_point' => $pickupPoint,
        ], 201);
    }

    /**
     * Update a pickup point for a student.
     */
    public function updatePickupPoint(Student $student, StudentPickupPoint $pickupPoint, UpdatePickupPointRequest $request): JsonResponse
    {
        if ($pickupPoint->student_id !== $student->id) {
            return response()->json(['message' => 'Pickup point not found for this student.'], 404);
        }

        $pickupPoint = app(UpdatePickupPointAction::class)($pickupPoint, $request->validated());

        return response()->json([
            'pickup_point' => $pickupPoint,
        ]);
    }

    /**
     * Delete a pickup point for a student.
     */
    public function deletePickupPoint(Student $student, StudentPickupPoint $pickupPoint): JsonResponse
    {
        if ($pickupPoint->student_id !== $student->id) {
            return response()->json(['message' => 'Pickup point not found for this student.'], 404);
        }

        app(DeletePickupPointAction::class)($pickupPoint);

        return response()->json([
            'message' => 'Pickup point deleted successfully.',
        ]);
    }

    /**
     * Set a pickup point as default for a student.
     */
    public function setDefaultPickupPoint(Student $student, StudentPickupPoint $pickupPoint): JsonResponse
    {
        if ($pickupPoint->student_id !== $student->id) {
            return response()->json(['message' => 'Pickup point not found for this student.'], 404);
        }

        $pickupPoint = app(SetDefaultPickupPointAction::class)($pickupPoint);

        return response()->json([
            'pickup_point' => $pickupPoint,
        ]);
    }

    /**
     * Reset a student's password (admin action).
     */
    public function updatePassword(AdminResetPasswordRequest $request, Student $student): JsonResponse
    {
        if (! $student->user_id) {
            return response()->json(['message' => 'Student does not have a user account.'], 422);
        }

        (new AdminResetPasswordAction)($student->user, $request->validated('password'));

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }

    /**
     * Update the status of a student.
     */
    public function updateStatus(Student $student, UpdateStudentStatusRequest $request): JsonResponse
    {
        $student = app(UpdateStudentStatusAction::class)($student, $request->validated());

        return response()->json([
            'student' => $student,
            'message' => 'Student status updated successfully.',
        ]);
    }

    /**
     * Remove a student from their assigned instructor.
     */
    public function removeStudent(Student $student): JsonResponse
    {
        if (! $student->instructor_id) {
            return response()->json(['message' => 'Student is not assigned to an instructor.'], 422);
        }

        $student = app(RemoveStudentFromInstructorAction::class)($student);

        return response()->json([
            'student' => $student,
            'message' => 'Student has been removed from the instructor.',
        ]);
    }

    /**
     * Get checklist items for a student (lazy-seeds defaults on first access).
     */
    public function checklist(Student $student): JsonResponse
    {
        $items = app(GetStudentChecklistAction::class)($student);

        return response()->json([
            'checklist_items' => $items,
        ]);
    }

    /**
     * Toggle a checklist item's checked state.
     */
    public function toggleChecklistItem(Student $student, StudentChecklistItem $checklistItem): JsonResponse
    {
        if ($checklistItem->student_id !== $student->id) {
            return response()->json(['message' => 'Checklist item not found for this student.'], 404);
        }

        $data = request()->validate([
            'is_checked' => 'required|boolean',
            'date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $checklistItem = app(ToggleChecklistItemAction::class)($checklistItem, $data);

        return response()->json([
            'checklist_item' => $checklistItem,
        ]);
    }
}
