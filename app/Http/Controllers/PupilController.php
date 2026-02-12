<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shared\Contact\CreateContactAction;
use App\Actions\Shared\Contact\DeleteContactAction;
use App\Actions\Shared\Contact\SetPrimaryContactAction;
use App\Actions\Shared\Contact\UpdateContactAction;
use App\Actions\Student\GetStudentDetailAction;
use App\Models\Contact;
use App\Models\Student;
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
