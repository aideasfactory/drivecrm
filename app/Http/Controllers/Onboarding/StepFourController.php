<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\ReleaseLegacyStepFourHoldsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepFourRequest;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Package;
use App\Services\CalendarService;
use App\Services\InstructorService;
use App\Support\BookingPayments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StepFourController extends Controller
{
    public function __construct(
        private CalendarService $calendarService,
        private InstructorService $instructorService,
        private ReleaseLegacyStepFourHoldsAction $releaseLegacyStepFourHolds,
    ) {}

    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');
        ($this->releaseLegacyStepFourHolds)($enquiry);
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

        // Get all available instructors for the dropdown (no postcode yet = none)
        $availableInstructors = $postcode
            ? $this->instructorService->findByPostcode($postcode)
            : collect();

        // Get available dates and time slots
        $availability = $instructorId ? $this->calendarService->getAvailability(
            instructorId: $instructorId,
            fromDate: now()->addDays(2), // 48 hours minimum
            toDate: now()->addDays(56),  // 8 weeks — must cover the month-view calendar
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
            'holdMinutes' => BookingPayments::learnerHoldMinutes(),
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
            toDate: now()->addDays(56),
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

        ($this->releaseLegacyStepFourHolds)($enquiry);

        $instructorId = $validated['instructor_id'] ?? $enquiry->getStepData(2)['instructor_id'] ?? null;
        $selectedCalendarItem = CalendarItem::with('calendar')->find($validated['calendar_item_id']);

        if (! $selectedCalendarItem
            || $selectedCalendarItem->calendar?->instructor_id !== (int) $instructorId
            || $selectedCalendarItem->calendar->date->toDateString() !== Carbon::parse($validated['date'])->toDateString()
            || ! $selectedCalendarItem->isEmptyAvailability()) {
            Log::info('Step 4: selected slot is no longer available', [
                'calendar_item_id' => $validated['calendar_item_id'],
                'enquiry_id' => $enquiry->id,
            ]);

            return back()->withErrors([
                'calendar_item_id' => 'Sorry, that time has just been booked. Please choose another time.',
            ]);
        }

        // The choice is only remembered on the enquiry; the diary is untouched
        // until the learner goes to payment (CreateOrderFromEnquiryAction).
        $enquiry->setStepData(4, [
            'date' => $selectedCalendarItem->calendar->date->toDateString(),
            'calendar_item_id' => $selectedCalendarItem->id,
            'start_time' => Carbon::parse($selectedCalendarItem->start_time)->format('H:i'),
            'end_time' => Carbon::parse($selectedCalendarItem->end_time)->format('H:i'),
            'instructor_id' => $instructorId,
        ]);
        $enquiry->current_step = max($enquiry->current_step, 4);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 5);
        $enquiry->save();

        Log::info('Step 4 slot choice saved', [
            'enquiry_id' => $enquiry->id,
            'calendar_item_id' => $selectedCalendarItem->id,
            'lessons_count' => $package->lessons_count,
        ]);

        return redirect()->route('onboarding.step5', ['uuid' => $enquiry->id]);
    }
}
