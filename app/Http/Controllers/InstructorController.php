<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreCalendarItemRequest;
use App\Http\Requests\StoreInstructorRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdateInstructorRequest;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\Student;
use App\Models\User;
use App\Services\InstructorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class InstructorController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
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
}
