<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Display the reports index page with analytics data.
     */
    public function index(): Response
    {
        $analytics = $this->reportService->getAvailabilityAnalytics();

        return Inertia::render('Reports/Index', [
            'analytics' => $analytics,
        ]);
    }
}
