<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Services\InstructorCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InstructorCalendarFeedController extends Controller
{
    public function __construct(
        protected InstructorCalendarService $calendarService,
    ) {}

    /**
     * Public iCalendar feed of an instructor's booked lessons and tests. The
     * secret token is the only credential, so calendar apps can poll it.
     */
    public function show(string $token): Response
    {
        $feed = $this->calendarService->calendarFeedForToken($token);

        abort_if($feed === null, 404);

        return response($feed, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="lessons.ics"',
            'Cache-Control' => 'private, max-age=300',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Replace the instructor's feed link. Calendars subscribed to the old link
     * stop updating.
     */
    public function reset(Instructor $instructor): JsonResponse
    {
        return response()->json([
            'message' => 'Calendar link reset. Re-add it to your calendar app.',
            'calendar_feed' => $this->calendarService->resetCalendarFeed($instructor),
        ]);
    }
}
