<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepThreeRequest;
use App\Models\Instructor;
use App\Models\Package;
use App\Services\PackageService;
use App\Services\PriceUpliftService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StepThreeController extends Controller
{
    public function __construct(
        protected PriceUpliftService $priceUpliftService,
        protected PackageService $packageService,
    ) {}

    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');
        $step1Data = $enquiry->getStepData(1);
        $step2Data = $enquiry->getStepData(2);

        // Get postcode from step 1
        $postcode = $step1Data['postcode'] ?? null;

        // Get selected instructor from step 2
        $instructorId = $step2Data['instructor_id'] ?? null;
        $selectedInstructor = null;

        if ($instructorId) {
            // Find the instructor and get their full details
            $instructor = Instructor::with(['user', 'locations'])->find($instructorId);
            if ($instructor) {
                // Parse meta JSON manually since casting might not be working
                $meta = is_string($instructor->meta) ? json_decode($instructor->meta, true) : ($instructor->meta ?? []);

                $selectedInstructor = [
                    'id' => $instructor->id,
                    'name' => $instructor->user->name,
                    'image' => $meta['avatar'] ?? null,
                    'experience' => $meta['experience'] ?? null,
                    'rating' => $meta['rating'] ?? null,
                    'bio' => $instructor->bio,
                    'address' => $instructor->address,
                ];
            }
        }

        // Per-instructor price uplift: the instructor chosen at step 2 may
        // carry a per-lesson uplift, applied in-memory so every price
        // accessor reflects the uplifted price.
        $uplift = $this->priceUpliftService->upliftForEnquiry($enquiry);
        $discount = $enquiry->getDiscountData();

        // Only Drive packages (no instructor_id) are offered during onboarding —
        // instructor-owned packages must never appear here, even when an
        // instructor was selected in step 2. Intro offers first, then by size.
        $packages = Package::whereNull('instructor_id')
            ->where('active', true)
            ->get()
            ->sortByDesc('is_intro_offer')
            ->sortBy('hours_total')
            ->values()
            ->map(function ($package) use ($uplift, $discount) {
                $this->priceUpliftService->applyUpliftToPackage($package, $uplift);
                $pricing = $this->packageService->calculateEnquiryPricing($package, $discount);

                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'promoted' => $package->promoted,
                    'formatted_total_price' => $package->formatted_total_price,
                    'formatted_lesson_price' => $package->formatted_lesson_price,
                    'booking_fee' => $package->booking_fee,
                    'digital_fee' => $package->digital_fee,
                    'total_price' => $package->total_price,
                    'total_with_fees' => '£'.number_format($pricing['total'], 2),
                    'weekly_payment' => '£'.number_format($pricing['weekly_payment'], 2),
                    'lessons_count' => $package->lessons_count,
                    'isIntroOffer' => $package->is_intro_offer,
                    'pricePerHour' => $package->less_price_pence,
                ];
            });

        return Inertia::render('Onboarding/Step3', [
            'uuid' => $enquiry->id,
            'currentStep' => 3,
            'totalSteps' => 6,
            'stepData' => $enquiry->getStepData(3),
            'postcode' => $postcode,
            'selectedInstructor' => $selectedInstructor,
            'packages' => $packages,
            'maxStepReached' => $enquiry->max_step_reached,
            'discount' => $discount,
        ]);
    }

    public function store(StepThreeRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        $enquiry->setStepData(3, $validated);
        $enquiry->current_step = max($enquiry->current_step, 3);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 4);
        $enquiry->save();

        return redirect()->route('onboarding.step4', ['uuid' => $enquiry->id]);
    }
}
