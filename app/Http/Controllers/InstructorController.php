<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreInstructorRequest;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdateInstructorRequest;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use App\Services\InstructorService;
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
        $instructor->load('user');

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
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password', 'password123')),
                'role' => UserRole::INSTRUCTOR,
            ]);

            // Create instructor profile
            Instructor::create([
                'user_id' => $user->id,
                'bio' => $request->input('bio'),
                'transmission_type' => $request->input('transmission_type'),
                'status' => $request->input('status', 'active'),
                'pdi_status' => $request->input('pdi_status'),
                'address' => $request->input('address'),
                'postcode' => $request->input('postcode'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'meta' => [
                    'phone' => $request->input('phone'),
                ],
            ]);
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

            // Update instructor profile
            $instructor->update([
                'bio' => $request->input('bio'),
                'transmission_type' => $request->input('transmission_type'),
                'status' => $request->input('status', 'active'),
                'pdi_status' => $request->input('pdi_status'),
                'address' => $request->input('address'),
                'postcode' => $request->input('postcode'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'meta' => array_merge($instructor->meta ?? [], [
                    'phone' => $request->input('phone'),
                ]),
            ]);
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
}
