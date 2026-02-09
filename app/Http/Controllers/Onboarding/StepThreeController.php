<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepThreeRequest;
use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StepThreeController extends Controller
{
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
        $packages = collect();

        if ($instructorId) {
            // Find the instructor and get their full details
            $instructor = Instructor::with(['user', 'locations', 'packages'])->find($instructorId);
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

                // Get packages and sort them (intro offers first, then by price)
                $packages = $instructor->packages
                    ->sortByDesc('is_intro_offer')
                    ->where('active', true)
                    ->sortBy('hours_total')
                    ->values()
                    ->map(function ($package) {
                        return [
                            'id' => $package->id,
                            'name' => $package->name,
                            'description' => $package->description,
                            'promoted' => $package->promoted,
                            'formatted_total_price' => $package->formatted_total_price,
                            'formatted_lesson_price' => $package->formatted_lesson_price,
                            'lessons_count' => $package->lessons_count,
                            'isIntroOffer' => $package->is_intro_offer,
                            'pricePerHour' => $package->less_price_pence,
                        ];
                    });
                // add all packages with a null instructor_id
                $packages = $packages->merge(Package::whereNull('instructor_id')
                    ->where('active', true)
                    ->get()
                    ->map(function ($package) {
                        return [
                            'id' => $package->id,
                            'name' => $package->name,
                            'description' => $package->description,
                            'promoted' => $package->promoted,
                            'formatted_total_price' => $package->formatted_total_price,
                            'formatted_lesson_price' => $package->formatted_lesson_price,
                            'lessons_count' => $package->lessons_count,
                            'isIntroOffer' => $package->is_intro_offer,
                            'pricePerHour' => $package->less_price_pence,
                        ];
                    }));
            }
        }

        return Inertia::render('Onboarding/Step3', [
            'uuid' => $enquiry->id,
            'currentStep' => 3,
            'totalSteps' => 6,
            'stepData' => $enquiry->getStepData(3),
            'postcode' => $postcode,
            'selectedInstructor' => $selectedInstructor,
            'packages' => $packages,
            'maxStepReached' => $enquiry->max_step_reached,
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
