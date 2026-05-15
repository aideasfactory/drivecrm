<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Report\GetInstructorAvailabilityAnalyticsAction;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        protected GetInstructorAvailabilityAnalyticsAction $getInstructorAvailabilityAnalytics
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
}
