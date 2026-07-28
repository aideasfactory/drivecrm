<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Services\EnquiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    public function __construct(
        protected EnquiryService $enquiryService,
    ) {}

    /**
     * Admin listing of all enquiries (both /onboarding and /booking sources).
     *
     * Status is derived from max_step_reached alone: 2 = completed enquiry
     * (2-step booking flow), 6 = full onboarding (legacy 6-step flow),
     * anything else = in progress. In-area comes from the snapshot written
     * by Booking\StepTwoController at data->steps.step2.in_area.
     */
    public function index(Request $request): Response
    {
        $status = $this->filterValue($request, 'status', ['all', 'completed', 'full_onboarding', 'in_progress']);
        $area = $this->filterValue($request, 'area', ['all', 'in_area', 'out_of_area', 'unknown']);

        $enquiries = $this->enquiryService
            ->getFiltered($status, $area)
            ->through(fn (Enquiry $enquiry) => $this->serializeEnquiry($enquiry));

        return Inertia::render('Enquiries/Index', [
            'enquiries' => $enquiries,
            'filters' => [
                'status' => $status,
                'area' => $area,
            ],
        ]);
    }

    private function filterValue(Request $request, string $key, array $allowed): string
    {
        $value = $request->query($key, 'all');

        return in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEnquiry(Enquiry $enquiry): array
    {
        $data = $enquiry->data ?? [];
        $step1 = $data['steps']['step1'] ?? [];
        $source = $data['source'] ?? 'onboarding';
        $totalSteps = $source === 'booking' ? 2 : 6;

        $status = match ($enquiry->max_step_reached) {
            2 => 'completed',
            6 => 'full_onboarding',
            default => 'in_progress',
        };

        return [
            'id' => $enquiry->id,
            'source' => $source,
            'total_steps' => $totalSteps,
            'current_step' => $enquiry->current_step,
            'max_step_reached' => $enquiry->max_step_reached,
            'status' => $status,
            'is_complete' => $status !== 'in_progress',
            'in_area' => Arr::get($data, 'steps.step2.in_area'),
            'first_name' => $step1['first_name'] ?? null,
            'last_name' => $step1['last_name'] ?? null,
            'email' => $step1['email'] ?? null,
            'phone' => $step1['phone'] ?? null,
            'postcode' => $step1['postcode'] ?? null,
            'transmission' => $step1['transmission'] ?? null,
            'created_at' => $enquiry->created_at?->toIso8601String(),
            'updated_at' => $enquiry->updated_at?->toIso8601String(),
            'data' => $data,
        ];
    }
}
