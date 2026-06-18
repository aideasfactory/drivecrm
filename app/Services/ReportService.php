<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Report\GetInstructorAvailabilityAnalyticsAction;
use App\Actions\Report\GetInvoiceDueWithin48HoursAction;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        protected GetInstructorAvailabilityAnalyticsAction $getInstructorAvailabilityAnalytics,
        protected GetInvoiceDueWithin48HoursAction $getInvoiceDueWithin48Hours
    ) {}

    /**
     * Get instructor availability and booking analytics.
     *
     * @return array{instructors: Collection, summary: array{total_available: int, total_booked: int, total_free: int, overall_utilization: float}}
     */
    public function getAvailabilityAnalytics(): array
    {
        return ($this->getInstructorAvailabilityAnalytics)();
    }

    /**
     * Get learners with an unpaid lesson payment due within the next 48 hours.
     *
     * @return array{rows: Collection, generated_at: string, window_hours: int}
     */
    public function getInvoiceDueWithin48Hours(): array
    {
        return ($this->getInvoiceDueWithin48Hours)();
    }
}
