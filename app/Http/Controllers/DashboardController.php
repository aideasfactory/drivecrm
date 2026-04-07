<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'metrics' => $this->dashboardService->getMetrics(),
        ]);
    }
}
