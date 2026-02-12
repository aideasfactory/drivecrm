<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shared\Contact\CreateContactAction;
use App\Actions\Shared\Contact\DeleteContactAction;
use App\Actions\Shared\Contact\SetPrimaryContactAction;
use App\Actions\Shared\Contact\UpdateContactAction;
use App\Actions\Shared\LogActivityAction;
use App\Http\Requests\StoreCalendarItemRequest;
use App\Http\Requests\StoreInstructorRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdateInstructorRequest;
use App\Models\CalendarItem;
use App\Models\Contact;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\Student;
use App\Models\User;
use App\Services\InstructorService;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class InstructorController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService,
        protected StripeService $stripeService
    ) {}

    /**
     * Display the instructors index page.
     */
    public function index(): Response
    {
        $instructors = Instructor::with('user')
            ->get()
            ->map(function (Instructor $instructor) {
                // Count students assigned to this instructor
                $pupilsCount = Student::where('instructor_id', $instructor->id)->count();

                // Determine connection status based on Stripe integration
                $connectionStatus = $instructor->stripe_account_id && $instructor->charges_enabled
                    ? 'connected'
                    : 'not_connected';

                return [
                    'id' => $instructor->id,
                    'name' => $instructor->user->name,
                    'email' => $instructor->user->email,
                    'connection_status' => $connectionStatus,
                    'pupils_count' => $pupilsCount,
                    'last_sync' => $instructor->updated_at->diffForHumans(),
                ];
            });

        return Inertia::render('Instructors/Index', [
            'instructors' => $instructors,
        ]);
    }

    /**
     * Display a specific instructor.
     */
    public function show(Instructor $instructor): Response
    {
        $instructor->load('user', 'locations');

        // Calculate statistics
        $stats = [
            'current_pupils' => Student::where('instructor_id', $instructor->id)->count(),
            'passed_pupils' => 0, // TODO: Implement when we track passed students
            'archived_pupils' => 0, // TODO: Implement when we add archiving
            'waiting_list' => 0, // TODO: Implement waiting list
            'open_enquiries' => 0, // TODO: Implement enquiries tracking
        ];

        // Calculate booking hours (demo data for now)
        $bookingHours = [
            'current_week' => 0, // TODO: Calculate from lessons/calendar
            'next_week' => 0, // TODO: Calculate from lessons/calendar
        ];

        // Get locations
        $locations = $this->instructorService->getLocations($instructor);

        return Inertia::render('Instructors/Show', [
            'instructor' => [
                'id' => $instructor->id,
                'name' => $instructor->user->name,
                'email' => $instructor->user->email,
                'onboarding_complete' => $instructor->onboarding_complete,
                'charges_enabled' => $instructor->charges_enabled,
                'payouts_enabled' => $instructor->payouts_enabled,
                'phone' => $instructor->meta['phone'] ?? null,
                'postcode' => $instructor->postcode,
                'bio' => $instructor->bio,
                'rating' => $instructor->rating,
                'transmission_type' => $instructor->transmission_type,
                'status' => $instructor->status,
                'stats' => $stats,
                'booking_hours' => $bookingHours,
                'locations' => $locations,
            ],
            'tab' => request()->query('tab', 'schedule'),
            'subtab' => request()->query('subtab', 'summary'),
            'student' => request()->query('student') ? (int) request()->query('student') : null,
        ]);
    }

    /**
     * Store a new instructor.
     */
    public function store(StoreInstructorRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            // Create user with instructor role
            $instructor = $this->instructorService->createInstructor($request->validated());
        });

        return redirect()->route('instructors.index');
    }

    /**
     * Update an existing instructor.
     */
    public function update(UpdateInstructorRequest $request, Instructor $instructor): RedirectResponse
    {
        DB::transaction(function () use ($request, $instructor) {
            // Update user information
            $instructor->user->update([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
            ]);

            $instructor = $this->instructorService->updateInstructor($instructor, $request->validated());
        });

        return redirect()->back();
    }

    /**
     * Get instructor's packages (both platform and bespoke).
     */
    public function packages(Instructor $instructor): JsonResponse
    {
        $packages = $this->instructorService->getPackages($instructor);

        return response()->json([
            'packages' => $packages,
        ]);
    }

    /**
     * Create a new bespoke package for the instructor.
     */
    public function createPackage(StorePackageRequest $request, Instructor $instructor): JsonResponse
    {
        $package = $this->instructorService->createPackage($instructor, $request->validated());

        return response()->json([
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'total_price_pence' => $package->total_price_pence,
                'lessons_count' => $package->lessons_count,
                'lesson_price_pence' => $package->lesson_price_pence,
                'formatted_total_price' => $package->formatted_total_price,
                'formatted_lesson_price' => $package->formatted_lesson_price,
                'active' => $package->active,
                'is_platform_package' => $package->isPlatformPackage(),
                'is_bespoke_package' => $package->isBespokePackage(),
            ],
        ], 201);
    }

    /**
     * Get instructor's coverage locations.
     */
    public function locations(Instructor $instructor): JsonResponse
    {
        $locations = $this->instructorService->getLocations($instructor);

        return response()->json([
            'locations' => $locations,
        ]);
    }

    /**
     * Add a new coverage location for the instructor.
     */
    public function storeLocation(StoreLocationRequest $request, Instructor $instructor): JsonResponse
    {
        $location = $this->instructorService->addLocation(
            $instructor,
            $request->input('postcode_sector')
        );

        (new LogActivityAction)($instructor, "Coverage area '{$location->postcode_sector}' added", 'profile', [
            'postcode_sector' => $location->postcode_sector,
        ]);

        return response()->json([
            'location' => [
                'id' => $location->id,
                'postcode_sector' => $location->postcode_sector,
            ],
        ], 201);
    }

    /**
     * Delete a coverage location.
     */
    public function destroyLocation(Instructor $instructor, Location $location): JsonResponse
    {
        // Verify the location belongs to this instructor
        if ($location->instructor_id !== $instructor->id) {
            return response()->json([
                'message' => 'Location not found for this instructor.',
            ], 404);
        }

        $this->instructorService->removeLocation($location);

        return response()->json([
            'message' => 'Location removed successfully.',
        ]);
    }

    /**
     * Get instructor's calendar with all calendar items for specified date range.
     */
    public function calendar(Instructor $instructor): JsonResponse
    {
        // Parse optional date range from query params
        $startDate = request()->query('start_date')
            ? Carbon::parse(request()->query('start_date'))
            : null;

        $endDate = request()->query('end_date')
            ? Carbon::parse(request()->query('end_date'))
            : null;

        $calendar = $this->instructorService->getCalendar($instructor, $startDate, $endDate);

        return response()->json([
            'calendar' => $calendar,
        ]);
    }

    /**
     * Create a new calendar item (time slot) for the instructor.
     */
    public function storeCalendarItem(StoreCalendarItemRequest $request, Instructor $instructor): JsonResponse
    {
        $calendarItem = $this->instructorService->addCalendarItem(
            $instructor,
            $request->input('date'),
            $request->input('start_time'),
            $request->input('end_time')
        );

        return response()->json([
            'calendar_item' => [
                'id' => $calendarItem->id,
                'calendar_id' => $calendarItem->calendar_id,
                'date' => $calendarItem->calendar->date->format('Y-m-d'),
                'start_time' => $calendarItem->start_time,
                'end_time' => $calendarItem->end_time,
                'is_available' => $calendarItem->is_available,
                'status' => $calendarItem->status ?? 'available',
            ],
        ], 201);
    }

    /**
     * Delete a calendar item (time slot).
     */
    public function destroyCalendarItem(Instructor $instructor, CalendarItem $calendarItem): JsonResponse
    {
        // Verify the calendar item belongs to this instructor
        if ($calendarItem->calendar->instructor_id !== $instructor->id) {
            return response()->json([
                'message' => 'Calendar item not found for this instructor.',
            ], 404);
        }

        try {
            $this->instructorService->removeCalendarItem($calendarItem);

            return response()->json([
                'message' => 'Calendar item removed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Start the Stripe Connect onboarding process for an instructor.
     */
    public function startStripeOnboarding(Instructor $instructor): JsonResponse
    {
        // If already has a Stripe account, redirect to refresh
        if ($instructor->stripe_account_id) {
            return response()->json([
                'message' => 'Instructor already has a Stripe account. Use refresh instead.',
                'action' => 'refresh',
            ], 400);
        }

        try {
            // Create Stripe Connect Account
            $accountResult = $this->stripeService->createConnectAccount($instructor);

            if (! $accountResult['success']) {
                return response()->json([
                    'message' => 'Failed to create Stripe account: '.$accountResult['error'],
                ], 500);
            }

            $instructor->stripe_account_id = $accountResult['account_id'];
            $instructor->save();

            // Create Account Link
            $returnUrl = route('instructors.stripe.onboarding.return', $instructor);
            $refreshUrl = route('instructors.stripe.onboarding.refresh', $instructor);

            $linkResult = $this->stripeService->createAccountLink(
                $instructor,
                $returnUrl,
                $refreshUrl
            );

            if (! $linkResult['success']) {
                return response()->json([
                    'message' => 'Failed to create account link: '.$linkResult['error'],
                ], 500);
            }

            return response()->json([
                'url' => $linkResult['url'],
                'stripe_account_id' => $accountResult['account_id'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start onboarding: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh the Stripe Connect onboarding link for an instructor.
     */
    public function refreshStripeOnboarding(Instructor $instructor): JsonResponse
    {
        if (! $instructor->stripe_account_id) {
            return response()->json([
                'message' => 'No Stripe account found. Please start onboarding first.',
            ], 400);
        }

        try {
            $returnUrl = route('instructors.stripe.onboarding.return', $instructor);
            $refreshUrl = route('instructors.stripe.onboarding.refresh', $instructor);

            $linkResult = $this->stripeService->createAccountLink(
                $instructor,
                $returnUrl,
                $refreshUrl
            );

            if (! $linkResult['success']) {
                return response()->json([
                    'message' => 'Failed to refresh onboarding link: '.$linkResult['error'],
                ], 500);
            }

            return response()->json([
                'url' => $linkResult['url'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refresh onboarding: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle return from Stripe Connect onboarding.
     */
    public function returnFromStripeOnboarding(Instructor $instructor): RedirectResponse
    {
        if (! $instructor->stripe_account_id) {
            return redirect()
                ->route('instructors.show', $instructor)
                ->with('error', 'No Stripe account found. Please start onboarding again.');
        }

        try {
            // Retrieve the Stripe account to check status
            $accountResult = $this->stripeService->retrieveAccount($instructor->stripe_account_id);

            if (! $accountResult['success']) {
                return redirect()
                    ->route('instructors.show', $instructor)
                    ->with('error', 'Failed to verify Stripe account status.');
            }

            $account = $accountResult['account'];

            // Update instructor record with current status
            $instructor->onboarding_complete = $account->details_submitted ?? false;
            $instructor->charges_enabled = $account->charges_enabled ?? false;
            $instructor->payouts_enabled = $account->payouts_enabled ?? false;
            $instructor->save();

            if ($instructor->onboarding_complete && $instructor->charges_enabled) {
                return redirect()
                    ->route('instructors.show', $instructor)
                    ->with('success', 'Stripe Connect onboarding completed successfully! Instructor can now create packages and receive payments.');
            }

            return redirect()
                ->route('instructors.show', $instructor)
                ->with('warning', 'Onboarding is not yet complete. Please complete all required information in Stripe.');

        } catch (\Exception $e) {
            return redirect()
                ->route('instructors.show', $instructor)
                ->with('error', 'Failed to verify onboarding status: '.$e->getMessage());
        }
    }

    /**
     * Get Stripe account status for an instructor.
     */
    public function stripeStatus(Instructor $instructor): JsonResponse
    {
        if (! $instructor->stripe_account_id) {
            return response()->json([
                'connected' => false,
                'onboarding_complete' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
            ]);
        }

        return response()->json([
            'connected' => true,
            'stripe_account_id' => $instructor->stripe_account_id,
            'onboarding_complete' => $instructor->onboarding_complete,
            'charges_enabled' => $instructor->charges_enabled,
            'payouts_enabled' => $instructor->payouts_enabled,
        ]);
    }

    /**
     * Handle instructor account deletion request.
     */
    public function requestDeletion(Instructor $instructor): JsonResponse
    {
        $adminEmail = config('mail.admin_address', config('mail.from.address'));
        $instructorName = $instructor->user->name;
        $instructorEmail = $instructor->user->email;

        Mail::raw(
            "Instructor Account Deletion Request\n\n".
            "Name: {$instructorName}\n".
            "Email: {$instructorEmail}\n".
            "Instructor ID: {$instructor->id}\n\n".
            'This instructor has requested their account be deleted. Please review and process this request.',
            function ($message) use ($adminEmail, $instructorName) {
                $message->to($adminEmail)
                    ->subject("Account Deletion Request: {$instructorName}");
            }
        );

        return response()->json([
            'message' => 'Account deletion request has been submitted. An administrator will review your request.',
        ]);
    }

    /**
     * Get emergency contacts for an instructor.
     */
    public function contacts(Instructor $instructor): JsonResponse
    {
        $contacts = $instructor->contacts()->orderByDesc('is_primary')->orderBy('name')->get();

        return response()->json([
            'contacts' => $contacts,
        ]);
    }

    /**
     * Store a new emergency contact for an instructor.
     */
    public function storeContact(Instructor $instructor): JsonResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        $contact = (new CreateContactAction)($instructor, $data);

        return response()->json([
            'contact' => $contact,
        ], 201);
    }

    /**
     * Update an emergency contact for an instructor.
     */
    public function updateContact(Instructor $instructor, Contact $contact): JsonResponse
    {
        if ($contact->contactable_id !== $instructor->id || $contact->contactable_type !== Instructor::class) {
            return response()->json(['message' => 'Contact not found for this instructor.'], 404);
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
     * Delete an emergency contact for an instructor.
     */
    public function deleteContact(Instructor $instructor, Contact $contact): JsonResponse
    {
        if ($contact->contactable_id !== $instructor->id || $contact->contactable_type !== Instructor::class) {
            return response()->json(['message' => 'Contact not found for this instructor.'], 404);
        }

        (new DeleteContactAction)($contact);

        return response()->json([
            'message' => 'Contact deleted successfully.',
        ]);
    }

    /**
     * Set an emergency contact as primary for an instructor.
     */
    public function setPrimaryContact(Instructor $instructor, Contact $contact): JsonResponse
    {
        if ($contact->contactable_id !== $instructor->id || $contact->contactable_type !== Instructor::class) {
            return response()->json(['message' => 'Contact not found for this instructor.'], 404);
        }

        $contact = (new SetPrimaryContactAction)($contact);

        return response()->json([
            'contact' => $contact,
        ]);
    }

    /**
     * Get all students (pupils) belonging to an instructor.
     */
    public function pupils(Instructor $instructor): JsonResponse
    {
        $search = request()->query('search');
        $pupils = $this->instructorService->getPupils($instructor, $search);

        return response()->json([
            'pupils' => $pupils,
        ]);
    }

    /**
     * Send a broadcast message to all of an instructor's students.
     */
    public function broadcastMessage(Instructor $instructor): JsonResponse
    {
        $data = request()->validate([
            'message' => 'required|string|max:5000',
        ]);

        $messages = $this->instructorService->broadcastMessage($instructor, $data['message']);

        return response()->json([
            'message' => 'Broadcast message sent successfully.',
            'recipients_count' => $messages->count(),
        ]);
    }

    /**
     * Store a new pupil for an instructor.
     */
    public function storePupil(Instructor $instructor): JsonResponse
    {
        $data = request()->validate([
            'first_name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'owns_account' => 'boolean',
        ]);

        $student = $this->instructorService->addPupil($instructor, $data);

        return response()->json([
            'message' => 'Pupil created successfully.',
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name.' '.$student->surname,
                'email' => $student->email,
            ],
        ], 201);
    }

    /**
     * Get activity logs for an instructor.
     */
    public function activityLogs(Instructor $instructor): JsonResponse
    {
        $query = $instructor->activityLogs()->recent();

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
