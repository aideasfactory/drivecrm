<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FilterLessonsBookedRequest;
use App\Services\LessonsBookedService;
use Inertia\Inertia;
use Inertia\Response;

class LessonsBookedController extends Controller
{
    public function __construct(
        protected LessonsBookedService $lessonsBookedService,
    ) {}

    public function index(FilterLessonsBookedRequest $request): Response
    {
        $filters = $request->filters();
        $listing = $this->lessonsBookedService->list($filters);

        return Inertia::render('LessonsBooked/Index', [
            'bookings' => $listing['bookings'],
            'counts' => $listing['counts'],
            'filters' => $filters,
        ]);
    }
}
