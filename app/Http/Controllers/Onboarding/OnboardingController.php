<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    /**
     * Entry point — create new enquiry and redirect to step 1.
     * Accepts optional query parameters:
     *   ?discount=<uuid>
     *   ?first_name=<string>&last_name=<string>&email=<string> — prefill step 1
     *   ?instructor_id=<int> — prefill step 2 (bypass instructor selection)
     *   ?staff_booking=1 — admin/bookings team booking on a student's behalf
     *                      (only honoured for signed-in owner users, including
     *                      owners with restricted admin access)
     */
    public function start(Request $request): RedirectResponse
    {
        $data = [
            'current_step' => 1,
            'steps' => [],
        ];

        // Validate and attach discount code if provided
        $discountUuid = $request->query('discount');
        if ($discountUuid) {
            $discountCode = DiscountCode::query()
                ->where('id', $discountUuid)
                ->where('active', true)
                ->first();

            if ($discountCode) {
                $data['discount'] = [
                    'id' => $discountCode->id,
                    'label' => $discountCode->label,
                    'percentage' => $discountCode->percentage,
                ];
            }
        }

        // Store prefill data for reuse from lessons page
        $prefill = [];
        if ($request->filled('first_name')) {
            $prefill['first_name'] = $request->query('first_name');
        }
        if ($request->filled('last_name')) {
            $prefill['last_name'] = $request->query('last_name');
        }
        if ($request->filled('email')) {
            $prefill['email'] = $request->query('email');
        }
        if ($request->filled('instructor_id')) {
            $instructor = Instructor::find($request->query('instructor_id'));
            if ($instructor) {
                $prefill['instructor_id'] = $instructor->id;
            }
        }

        if (! empty($prefill)) {
            $data['prefill'] = $prefill;
        }

        // Google Ads landing URLs carry ?gclid=…; capture it here so it
        // survives the redirect into the step flow and can be forwarded to
        // downstream tools (Bird CRM, admin email, GTM) as source "Google ads".
        $gclid = trim((string) $request->query('gclid', ''));
        if ($gclid !== '') {
            $data['tracking'] = [
                'gclid' => $gclid,
                'source' => 'Google ads',
            ];
        }

        $staffUser = $this->staffBookingUser($request);

        if ($staffUser instanceof User) {
            $data['staff_booking'] = [
                'user_id' => $staffUser->id,
                'name' => $staffUser->name,
            ];
        }

        $maxStep = 1;

        // If instructor_id is prefilled, auto-populate step 2 data and advance
        if (isset($prefill['instructor_id'])) {
            $data['steps']['step2'] = [
                'instructor_id' => $prefill['instructor_id'],
            ];
            $maxStep = 3;
        }

        $enquiry = Enquiry::create([
            'data' => $data,
            'current_step' => 1,
            'max_step_reached' => $maxStep,
        ]);

        return redirect()->route('onboarding.step1', ['uuid' => $enquiry->id]);
    }

    /**
     * Bookings-team entry. Auth runs before the enquiry exists, so a missing
     * session cannot silently fall through to the learner payment page.
     * Restricted owners are owners and follow the same invoice path.
     */
    public function startStaff(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isOwner()) {
            abort(403, 'Only admin accounts can book lessons on a student\'s behalf.');
        }

        $request->query->set('staff_booking', '1');

        return $this->start($request);
    }

    /**
     * The signed-in admin behind a bookings-team form, or null for a learner checkout.
     * Full and restricted owners both qualify. Guests and other roles do not.
     */
    private function staffBookingUser(Request $request): ?User
    {
        if (! $request->boolean('staff_booking')) {
            return null;
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->isOwner()) {
            return null;
        }

        return $user;
    }

    /**
     * Completion page after successful payment
     */
    public function complete(Request $request)
    {
        $enquiry = $request->get('enquiry');

        // Ensure payment was completed
        if ($enquiry->current_step < 6) {
            return redirect()->route('onboarding.step'.$enquiry->current_step, [
                'uuid' => $enquiry->id,
            ]);
        }

        $step6 = $enquiry->getStepData(6) ?? [];

        $order = ! empty($step6['order_id']) ? Order::find($step6['order_id']) : null;
        $isPaid = $order && ($order->isActive() || $order->status === OrderStatus::COMPLETED);

        return Inertia::render('Onboarding/Complete', [
            'enquiry' => $enquiry,
            // What the learner paid today, itemised (full amount, or week 1 for weekly)
            'payment' => $isPaid ? [
                'payment_mode' => $order->payment_mode->value,
                'includes_test_pass_guarantee' => (bool) $order->includes_test_pass_guarantee,
                'first_payment' => $order->firstPaymentBreakdown(),
                'weekly_instalment' => $order->isWeekly() ? $order->formatted_weekly_instalment : null,
            ] : null,
            'staffBooking' => $enquiry->isStaffBooking() ? [
                'payment_mode' => $step6['payment_mode'] ?? null,
                'payment_link_sent_to' => $step6['payment_link_sent_to'] ?? null,
                'hold_deadline' => $step6['hold_deadline'] ?? null,
            ] : null,
        ]);
    }
}
