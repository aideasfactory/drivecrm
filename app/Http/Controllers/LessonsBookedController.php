<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StaffBooking\ListStaffBookedLessonsAction;
use App\Http\Requests\FilterLessonsBookedRequest;
use Inertia\Inertia;
use Inertia\Response;

class LessonsBookedController extends Controller
{
    public function index(FilterLessonsBookedRequest $request, ListStaffBookedLessonsAction $listStaffBookedLessons): Response
    {
        $paymentState = $request->paymentState();
        $listing = $listStaffBookedLessons($paymentState);

        return Inertia::render('LessonsBooked/Index', [
            'bookings' => $listing['bookings'],
            'counts' => $listing['counts'],
            'filters' => [
                'payment' => $paymentState,
            ],
        ]);
    }
}
