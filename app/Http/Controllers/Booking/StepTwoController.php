<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Mail\BookingEnquirySubmittedMail;
use App\Models\Instructor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class StepTwoController extends Controller
{
    /**
     * Result page: checks the configured booking instructor's coverage against
     * the postcode collected in step 1. Renders an in-area or out-of-area message.
     */
    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');
        $step1Data = $enquiry->getStepData(1);

        $postcode = $step1Data['postcode'] ?? null;
        $instructorId = config('booking.instructor_id');

        $inArea = $this->isInArea($postcode, $instructorId);

        // First-visit detection: only send the admin email when step 2 lands for the
        // first time. Reloads / browser-back must not re-send.
        $isFirstVisit = $enquiry->getStepData(2) === null;

        // Persist the coverage outcome on the enquiry so the leads tooling can see it.
        $enquiry->setStepData(2, [
            'instructor_id' => $instructorId,
            'in_area' => $inArea,
        ]);
        $enquiry->current_step = max($enquiry->current_step, 2);
        $enquiry->save();

        if ($isFirstVisit) {
            $this->notifyAdmin($enquiry);
        }

        return Inertia::render('Booking/Step2', [
            'uuid' => $enquiry->id,
            'currentStep' => 2,
            'totalSteps' => 2,
            'postcode' => $postcode,
            'inArea' => $inArea,
            'maxStepReached' => $enquiry->max_step_reached,
        ]);
    }

    private function notifyAdmin(\App\Models\Enquiry $enquiry): void
    {
        $adminEmail = config('booking.admin_email');

        if (! $adminEmail) {
            Log::info('Booking enquiry submitted but ADMIN_EMAIL is not configured; no admin email sent.', [
                'enquiry_id' => $enquiry->id,
            ]);

            return;
        }

        Mail::to($adminEmail)->send(new BookingEnquirySubmittedMail($enquiry));
    }

    private function isInArea(?string $postcode, mixed $instructorId): bool
    {
        if (! $postcode || ! $instructorId) {
            return false;
        }

        $sector = $this->extractPostcodeSector($postcode);

        if (! $sector) {
            return false;
        }

        return Instructor::query()
            ->active()
            ->where('id', $instructorId)
            ->whereHas('locations', function ($query) use ($sector) {
                $query->where('postcode_sector', $sector);
            })
            ->exists();
    }

    private function extractPostcodeSector(string $postcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($postcode)));

        if (empty($normalized) || strlen($normalized) < 5) {
            return null;
        }

        return substr($normalized, 0, -3);
    }
}
