<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    /**
     * Admin listing of all enquiries (both /onboarding and /booking sources).
     * "Completed" is computed as max_step_reached >= total steps for the source.
     */
    public function index(): Response
    {
        $enquiries = Enquiry::query()
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(function (Enquiry $enquiry) {
                $data = $enquiry->data ?? [];
                $step1 = $data['steps']['step1'] ?? [];
                $source = $data['source'] ?? 'onboarding';
                $totalSteps = $source === 'booking' ? 2 : 6;

                return [
                    'id' => $enquiry->id,
                    'source' => $source,
                    'total_steps' => $totalSteps,
                    'current_step' => $enquiry->current_step,
                    'max_step_reached' => $enquiry->max_step_reached,
                    'is_complete' => $enquiry->max_step_reached >= $totalSteps,
                    'first_name' => $step1['first_name'] ?? null,
                    'last_name' => $step1['last_name'] ?? null,
                    'email' => $step1['email'] ?? null,
                    'phone' => $step1['phone'] ?? null,
                    'postcode' => $step1['postcode'] ?? null,
                    'created_at' => $enquiry->created_at?->toIso8601String(),
                    'updated_at' => $enquiry->updated_at?->toIso8601String(),
                    'data' => $data,
                ];
            });

        return Inertia::render('Enquiries/Index', [
            'enquiries' => $enquiries,
        ]);
    }
}
