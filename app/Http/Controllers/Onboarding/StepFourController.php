<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepFourRequest;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Package;
use App\Services\CalendarService;
use App\Services\InstructorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StepFourController extends Controller
{
    public function __construct(
        private CalendarService $calendarService,
        private InstructorService $instructorService
    ) {}

    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');
        $step2Data = $enquiry->getStepData(2);
        $step1Data = $enquiry->getStepData(1);
        $postcode = $step1Data['postcode'] ?? null;
        $instructorId = $step2Data['instructor_id'] ?? null;

        // If no instructor selected, get first available
        if (! $instructorId) {
            $instructor = Instructor::with('user')->where('status', 'active')->first();
            $instructorId = $instructor?->id;
        } else {
            $instructor = Instructor::with('user')->find($instructorId);
        }

        // Get all available instructors for the dropdown
        $availableInstructors = $this->instructorService->findByPostcode($postcode);

        // Get available dates and time slots
        $availability = $instructorId ? $this->calendarService->getAvailability(
            instructorId: $instructorId,
            fromDate: now()->addDays(2), // 48 hours minimum
            toDate: now()->addDays(10),  // 10 days maximum
        ) : ['dates' => [], 'default_selected_index' => null];

        return Inertia::render('Onboarding/Step4', [
            'uuid' => $enquiry->id,
            'currentStep' => 4,
            'totalSteps' => 6,
            'stepData' => $enquiry->getStepData(4),
            'maxStepReached' => $enquiry->max_step_reached,
            'instructor' => $instructor,
            'availableInstructors' => $availableInstructors,
            'availability' => $availability,
            'disabledDates' => [
                now()->format('Y-m-d'),           // Today
                now()->addDay()->format('Y-m-d'), // Tomorrow
            ],
        ]);
    }

    /**
     * Get instructor tags based on transmission type and other attributes
     */
    private function getInstructorTags(Instructor $instructor): array
    {
        $tags = [];

        if ($instructor->transmission_type === 'manual') {
            $tags[] = 'Manual';
        }
        if ($instructor->transmission_type === 'automatic') {
            $tags[] = 'Automatic';
        }
        if ($instructor->transmission_type === 'both' || empty($instructor->transmission_type)) {
            $tags[] = 'Manual';
            $tags[] = 'Automatic';
        }

        // Add additional tags based on meta data if available
        if ($instructor->meta['intensive_courses'] ?? false) {
            $tags[] = 'Intensive courses';
        }
        if ($instructor->meta['pass_plus'] ?? false) {
            $tags[] = 'Pass Plus';
        }

        return empty($tags) ? ['Manual', 'Automatic'] : $tags;
    }

    /**
     * Dynamic availability fetch when instructor changes
     * Returns JSON for partial reload
     */
    public function availability(Request $request, string $uuid, string $instructor)
    {
        $availability = $this->calendarService->getAvailability(
            instructorId: $instructor,
            fromDate: now()->addDays(2),
            toDate: now()->addDays(10),
        );

        // Return as Inertia partial or JSON
        if ($request->wantsJson()) {
            return response()->json([
                'availability' => $availability,
            ]);
        }

        return Inertia::render('Onboarding/Step4', [
            'availability' => $availability,
        ])->only(['availability']);
    }

    public function store(StepFourRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        Log::info('=== ONBOARDING STEP 4: Schedule Selection ===', [
            'enquiry_id' => $enquiry->id,
            'validated_data' => $validated,
        ]);

        // If instructor changed, update step 2 as well
        if (! empty($validated['instructor_id'])) {
            $enquiry->setStepData(2, ['instructor_id' => $validated['instructor_id']]);
        }

        // Get package to know how many lessons we need to reserve
        $step3 = $enquiry->getStepData(3) ?? [];
        $package = Package::find($step3['package_id']);

        if (! $package) {
            Log::error('Package not found in Step 4', [
                'enquiry_id' => $enquiry->id,
                'step3_data' => $step3,
            ]);

            return redirect()
                ->route('onboarding.step3', ['uuid' => $enquiry->id])
                ->with('error', 'Please select a package first.');
        }

        Log::info('Package loaded for calendar reservation', [
            'package_id' => $package->id,
            'lessons_count' => $package->lessons_count,
            'enquiry_id' => $enquiry->id,
        ]);

        // Update the selected calendar item to draft status
        $selectedCalendarItem = CalendarItem::find($validated['calendar_item_id']);

        if (! $selectedCalendarItem) {
            Log::error('Calendar item not found', [
                'calendar_item_id' => $validated['calendar_item_id'],
                'enquiry_id' => $enquiry->id,
            ]);

            return back()->with('error', 'Selected time slot is no longer available.');
        }

        // Update selected calendar item
        $selectedCalendarItem->update([
            'is_available' => false,
            'status' => 'draft',
        ]);

        Log::info('Updated selected calendar item to draft', [
            'calendar_item_id' => $selectedCalendarItem->id,
            'status' => 'draft',
            'is_available' => false,
            'enquiry_id' => $enquiry->id,
        ]);

        // Collect all calendar_item_ids (first one + additional ones)
        $calendarItemIds = [$selectedCalendarItem->id];

        // Create additional calendar items for remaining lessons (weekly intervals)
        $lessonsToCreate = $package->lessons_count - 1; // -1 because we already have the first one
        $firstLessonDate = Carbon::parse($validated['date']);
        $instructorId = $validated['instructor_id'] ?? $enquiry->getStepData(2)['instructor_id'] ?? null;

        Log::info('Creating additional draft calendar items', [
            'lessons_to_create' => $lessonsToCreate,
            'first_lesson_date' => $firstLessonDate->toDateString(),
            'instructor_id' => $instructorId,
            'enquiry_id' => $enquiry->id,
        ]);

        for ($i = 1; $i <= $lessonsToCreate; $i++) {
            // Calculate next week's date
            $nextLessonDate = $firstLessonDate->copy()->addWeeks($i);

            // Get or create calendar for this date (UNIQUE per instructor per date)
            $calendar = Calendar::firstOrCreate(
                [
                    'instructor_id' => $instructorId,
                    'date' => $nextLessonDate->toDateString(),
                ],
                [
                    'instructor_id' => $instructorId,
                    'date' => $nextLessonDate->toDateString(),
                ]
            );

            // Check if we reused an existing calendar or created a new one
            $calendarAction = $calendar->wasRecentlyCreated ? 'created' : 'reused existing';

            Log::info("Calendar record {$calendarAction} for instructor", [
                'calendar_id' => $calendar->id,
                'instructor_id' => $instructorId,
                'date' => $nextLessonDate->toDateString(),
                'was_recently_created' => $calendar->wasRecentlyCreated,
                'action' => $calendarAction,
                'enquiry_id' => $enquiry->id,
            ]);

            // Create calendar item for this week
            $calendarItem = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'is_available' => false,
                'status' => 'draft',
            ]);

            $calendarItemIds[] = $calendarItem->id;

            Log::info('Created draft calendar item for future week', [
                'calendar_item_id' => $calendarItem->id,
                'calendar_id' => $calendar->id,
                'calendar_action' => $calendarAction,
                'week_number' => $i + 1,
                'date' => $nextLessonDate->toDateString(),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'enquiry_id' => $enquiry->id,
            ]);
        }

        Log::info('All draft calendar items created', [
            'total_items' => count($calendarItemIds),
            'calendar_item_ids' => $calendarItemIds,
            'enquiry_id' => $enquiry->id,
        ]);

        // Save to step 4 data
        $enquiry->setStepData(4, [
            'date' => $validated['date'],
            'calendar_item_id' => $validated['calendar_item_id'], // First calendar item ID
            'calendar_item_ids' => $calendarItemIds, // All calendar item IDs
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'instructor_id' => $instructorId,
        ]);
        $enquiry->current_step = max($enquiry->current_step, 4);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 5);
        $enquiry->save();

        Log::info('Step 4 data saved with draft calendar items', [
            'enquiry_id' => $enquiry->id,
            'calendar_items_count' => count($calendarItemIds),
        ]);

        return redirect()->route('onboarding.step5', ['uuid' => $enquiry->id]);
    }
}
