<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FilterEnquiriesRequest;
use App\Models\Enquiry;
use App\Services\EnquiryService;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function index(FilterEnquiriesRequest $request): Response
    {
        $filters = $request->filters();

        $enquiries = $this->enquiryService
            ->getFiltered($filters)
            ->through(fn (Enquiry $enquiry) => $this->serializeEnquiry($enquiry));

        return Inertia::render('Enquiries/Index', [
            'enquiries' => $enquiries,
            'filters' => $filters,
        ]);
    }

    /**
     * Download every enquiry matching the current list filters as CSV.
     */
    public function exportCsv(FilterEnquiriesRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $enquiries = $this->enquiryService->getFilteredForExport($filters);

        $filename = $this->exportFilename($filters);

        return response()->streamDownload(function () use ($enquiries): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Created at',
                'Source',
                'First name',
                'Last name',
                'Email',
                'Phone',
                'Postcode',
                'Transmission',
                'Current step',
                'Max step reached',
                'Total steps',
                'Status',
                'In area',
                'Instructor ID',
                'Privacy consent',
                'Marketing consent',
                'Consented at',
                'Tracking source',
                'GCLID',
            ]);

            foreach ($enquiries as $enquiry) {
                fputcsv($handle, $this->csvRow($enquiry));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  array{status: string, area: string, date_from: ?string, date_to: ?string, q: ?string}  $filters
     */
    private function exportFilename(array $filters): string
    {
        $range = ($filters['date_from'] && $filters['date_to'])
            ? $filters['date_from'].'_'.$filters['date_to']
            : now()->format('Y-m-d');

        return 'enquiries-'.$range.'.csv';
    }

    /**
     * @return list<string|int|null>
     */
    private function csvRow(Enquiry $enquiry): array
    {
        $row = $this->serializeEnquiry($enquiry);
        $step2 = $row['data']['steps']['step2'] ?? [];
        $tracking = $row['data']['tracking'] ?? [];

        return [
            $row['id'],
            $enquiry->created_at?->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'),
            $row['source'] === 'booking' ? 'Booking' : 'Onboarding',
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['postcode'],
            $this->transmissionLabel($row['transmission']),
            $row['current_step'],
            $row['max_step_reached'],
            $row['total_steps'],
            match ($row['status']) {
                'completed' => 'Completed',
                'full_onboarding' => 'Full onboarding',
                default => 'In progress',
            },
            match ($row['in_area']) {
                true => 'In area',
                false => 'Out of area',
                default => '',
            },
            $step2['instructor_id'] ?? null,
            $enquiry->privacy_consent ? 'Yes' : 'No',
            $enquiry->marketing_consent ? 'Yes' : 'No',
            $enquiry->consented_at?->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'),
            $tracking['source'] ?? null,
            $tracking['gclid'] ?? null,
        ];
    }

    private function transmissionLabel(?string $value): string
    {
        return match ($value) {
            'manual' => 'Manual',
            'automatic' => 'Automatic',
            'both' => 'Either',
            default => '',
        };
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
