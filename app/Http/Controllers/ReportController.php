<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Display the reports hub: a list of available reports.
     */
    public function index(): Response
    {
        return Inertia::render('Reports/Index', [
            'reports' => $this->availableReports(),
        ]);
    }

    /**
     * Display the instructor availability & booking analytics report.
     */
    public function availability(): Response
    {
        return Inertia::render('Reports/Availability', [
            'analytics' => $this->reportService->getAvailabilityAnalytics(),
        ]);
    }

    /**
     * Display learners with an unpaid lesson payment due within the next 48 hours.
     */
    public function invoiceDue(): Response
    {
        return Inertia::render('Reports/InvoiceDue', [
            'report' => $this->reportService->getInvoiceDueWithin48Hours(),
        ]);
    }

    /**
     * Download the instructor availability analytics as a CSV file.
     */
    public function exportAvailability(): StreamedResponse
    {
        $analytics = $this->reportService->getAvailabilityAnalytics();

        return response()->streamDownload(function () use ($analytics): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Instructor', 'Total Slots', 'Booked', 'Free', 'Utilization %']);

            foreach ($analytics['instructors'] as $instructor) {
                fputcsv($handle, [
                    $instructor['name'],
                    $instructor['total_slots'],
                    $instructor['total_booked'],
                    $instructor['total_free'],
                    $instructor['utilization_rate'],
                ]);
            }

            fclose($handle);
        }, 'instructor-availability-'.CarbonImmutable::now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Download the invoice-due-within-48-hours report as a CSV file.
     */
    public function exportInvoiceDue(): StreamedResponse
    {
        $report = $this->reportService->getInvoiceDueWithin48Hours();

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Learner', 'Phone', 'Email', 'Instructor', 'Lesson Date', 'Lesson Time', 'Amount Due', 'Invoice Due Date']);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['learner_name'],
                    $row['learner_phone'],
                    $row['learner_email'],
                    $row['instructor_name'],
                    $row['lesson_date'],
                    $row['lesson_time'],
                    $row['amount_due'],
                    $row['due_date'],
                ]);
            }

            fclose($handle);
        }, 'invoice-due-48h-'.CarbonImmutable::now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Metadata for the reports listed on the hub page.
     *
     * @return array<int, array{key: string, title: string, description: string, icon: string, route: string}>
     */
    protected function availableReports(): array
    {
        return [
            [
                'key' => 'availability',
                'title' => 'Instructor Availability & Booking',
                'description' => 'Slot utilisation per instructor — booked vs free capacity.',
                'icon' => 'CalendarCheck',
                'route' => route('reports.availability'),
            ],
            [
                'key' => 'invoice-due',
                'title' => 'Invoice Due (2 days away)',
                'description' => 'Learners with an unpaid lesson two days from today — chase manually.',
                'icon' => 'ReceiptText',
                'route' => route('reports.invoice-due'),
            ],
        ];
    }
}
