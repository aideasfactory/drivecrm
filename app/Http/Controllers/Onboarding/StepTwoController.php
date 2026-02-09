<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepTwoRequest;
use App\Services\InstructorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StepTwoController extends Controller
{
    public function __construct(
        private InstructorService $instructorService
    ) {}

    public function show(Request $request)
    {

        $enquiry = $request->get('enquiry');
        $step1Data = $enquiry->getStepData(1);

        // Get postcode from step 1
        $postcode = $step1Data['postcode'] ?? null;

        // Search for instructors by postcode sector
        $instructors = $this->instructorService->findByPostcode($postcode);

        $instructors->each(function ($instructor) {
            $instructor->next_available = $this->instructorService->nextAvailableDate($instructor);
        });

        return Inertia::render('Onboarding/Step2', [
            'uuid' => $enquiry->id,
            'currentStep' => 2,
            'totalSteps' => 6,
            'stepData' => $enquiry->getStepData(2),
            'postcode' => $postcode,
            'instructors' => $instructors,
            'googleMapsApiKey' => config('services.google.maps_api_key'),
            'maxStepReached' => $enquiry->max_step_reached,
        ]);
    }

    public function store(StepTwoRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        $enquiry->setStepData(2, $validated);
        $enquiry->current_step = max($enquiry->current_step, 2);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 3);
        $enquiry->save();

        return redirect()->route('onboarding.step3', ['uuid' => $enquiry->id]);
    }
}
