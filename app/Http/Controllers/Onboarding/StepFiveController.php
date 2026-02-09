<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepFiveRequest;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StepFiveController extends Controller
{
    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');

        // Gather all previous step data for review
        $step1 = $enquiry->getStepData(1) ?? [];
        $step2 = $enquiry->getStepData(2) ?? [];
        $step3 = $enquiry->getStepData(3) ?? [];
        $step4 = $enquiry->getStepData(4) ?? [];
        $step5 = $enquiry->getStepData(5) ?? [];

        // Load instructor with user relationship
        $instructor = null;
        if (! empty($step2['instructor_id'])) {
            $instructor = Instructor::with('user')->find($step2['instructor_id']);
        }

        // Load package
        $package = null;
        if (! empty($step3['package_id'])) {
            $package = Package::find($step3['package_id']);
        }

        // Load calendar item for lesson time
        $calendarItem = null;
        if (! empty($step4['calendar_item_id'])) {
            $calendarItem = CalendarItem::with('calendar')
                ->find($step4['calendar_item_id']);
        }

        // Load pickup location
        $pickupLocation = null;
        if (! empty($step3['pickup_location_id'])) {
            $pickupLocation = Location::find($step3['pickup_location_id']);
        }

        // Calculate pricing
        $packagePrice = $package ? (float) $package->price : 0;
        $bookingFee = 19.99;
        $promoDiscount = 0;

        // Apply promo code if exists
        if (! empty($step5['promo_code'])) {
            $promoCode = strtolower($step5['promo_code']);
            if ($promoCode === 'save10') {
                $promoDiscount = $packagePrice * 0.10;
            } elseif ($promoCode === 'save20') {
                $promoDiscount = $packagePrice * 0.20;
            }
        }

        $totalPrice = $packagePrice + $bookingFee - $promoDiscount;

        return Inertia::render('Onboarding/Step5', [
            'uuid' => $enquiry->id,
            'currentStep' => 5,
            'totalSteps' => 6,
            'stepData' => $step5,
            'maxStepReached' => $enquiry->max_step_reached,

            // Instructor details
            'instructor' => $instructor,

            // Package details
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'formatted_total_price' => $package->formatted_total_price,
                'formatted_lesson_price' => $package->formatted_lesson_price,
                'booking_fee' => $package->booking_fee,
                'digital_fee' => $package->digital_fee,
                'total_price' => $package->total_price,
                'weekly_payment' => $package->weekly_payment,
                'lessons_count' => $package->lessons_count,
                'isIntroOffer' => $package->is_intro_offer,
                'pricePerHour' => $package->less_price_pence,
            ] : null,

            // Schedule details
            'schedule' => [
                'date' => $step4['date'] ?? null,
                'start_time' => $step4['start_time'] ?? null,
                'end_time' => $step4['end_time'] ?? null,
                'formatted_date' => ! empty($step4['date'])
                    ? \Carbon\Carbon::parse($step4['date'])->format('l, F j, Y')
                    : null,
            ],

            // Contact details
            'contact' => [
                'email' => $step1['email'] ?? null,
                'phone' => $step1['phone'] ?? null,
                'pickup_location' => $pickupLocation ? [
                    'id' => $pickupLocation->id,
                    'address' => $pickupLocation->address,
                    'city' => $pickupLocation->city,
                    'postcode' => $pickupLocation->postcode,
                ] : [
                    'address_line_1' => $step5['pickup_address_line_1'] ?? $step3['pickup_address'] ?? '',
                    'address_line_2' => $step5['pickup_address_line_2'] ?? '',
                    'city' => $step5['pickup_city'] ?? 'London',
                    'postcode' => $step5['pickup_postcode'] ?? $step1['postcode'] ?? '',
                ],
            ],

            // Learner details (if booking for someone else)
            'learner' => [
                'is_self' => ! ($step5['booking_for_someone_else'] ?? false),
                'booking_for_someone_else' => $step5['booking_for_someone_else'] ?? false,
                'first_name' => $step5['learner_first_name'] ?? null,
                'last_name' => $step5['learner_last_name'] ?? null,
                'email' => $step5['learner_email'] ?? null,
                'phone' => $step5['learner_phone'] ?? null,
                'dob' => $step5['learner_dob'] ?? null,
            ],

            // Pricing summary
            'pricing' => [
                'package_price' => number_format($packagePrice, 2),
                'booking_fee' => number_format($bookingFee, 2),
                'promo_discount' => $promoDiscount > 0 ? number_format($promoDiscount, 2) : null,
                'total' => number_format($totalPrice, 2),
            ],

            // Available promo codes (for demo)
            'available_promos' => ['SAVE10', 'SAVE20'],

            // Pass back saved form data
            'pickup_address_line_1' => $step5['pickup_address_line_1'] ?? '',
            'pickup_address_line_2' => $step5['pickup_address_line_2'] ?? '',
            'pickup_city' => $step5['pickup_city'] ?? 'London',
            'pickup_postcode' => $step5['pickup_postcode'] ?? $step1['postcode'] ?? '',
            'postcode' => $step1['postcode'] ?? '',
        ]);
    }

    public function store(StepFiveRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        $enquiry->setStepData(5, $validated);

        // Check if this is moving forward (vs auto-save)
        if ($request->isMethod('post') && ! $request->has('auto_save')) {
            $enquiry->current_step = max($enquiry->current_step, 5);
            $enquiry->max_step_reached = max($enquiry->max_step_reached, 6);
            $enquiry->save();

            return redirect()->route('onboarding.step6', ['uuid' => $enquiry->id]);
        }

        // For auto-saves, just save and return success
        $enquiry->save();

        return back()->with('success', 'Progress saved');
    }
}
