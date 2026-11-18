<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CalendarFeedResource;
use App\Services\InstructorCalendarService;
use Illuminate\Http\Request;

class InstructorCalendarFeedController extends Controller
{
    public function __construct(
        protected InstructorCalendarService $calendarService,
    ) {}

    /**
     * Links to subscribe to the authenticated instructor's calendar feed.
     */
    public function show(Request $request): CalendarFeedResource
    {
        $instructor = $request->user()->instructor;

        abort_if($instructor === null, 403, 'Only instructors have a calendar feed.');

        return new CalendarFeedResource($this->calendarService->calendarFeedLinks($instructor));
    }

    /**
     * Replace the feed link; calendars subscribed to the old one stop updating.
     */
    public function reset(Request $request): CalendarFeedResource
    {
        $instructor = $request->user()->instructor;

        abort_if($instructor === null, 403, 'Only instructors have a calendar feed.');

        return new CalendarFeedResource($this->calendarService->resetCalendarFeed($instructor));
    }
}
