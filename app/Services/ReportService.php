<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Report\GetInstructorAvailabilityAnalyticsAction;

class ReportService
{
    public function __construct(
        protected GetInstructorAvailabilityAnalyticsAction $getInstructorAvailabilityAnalytics
    ) {}

    /**
     * Get instructor availability and booking analytics.
     *
     * @return array{instructors: \Illuminate\Support\Collection, summary: array{total_available: int, total_booked: int, total_free: int, overall_utilization: float}}
     */
    public function getAvailabilityAnalytics(): array
    {
        return ($this->getInstructorAvailabilityAnalytics)();
    }
}
