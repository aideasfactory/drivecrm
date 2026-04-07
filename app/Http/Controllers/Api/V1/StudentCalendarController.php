<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetCalendarItemsRequest;
use App\Http\Resources\V1\CalendarItemResource;
use App\Services\InstructorCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentCalendarController extends Controller
{
    public function __construct(
        protected InstructorCalendarService $calendarService
    ) {}

    /**
     * Return available calendar slots for the authenticated student's attached instructor.
     *
     * Students always see only available slots (travel and practical test items excluded)
     * and draft items are always excluded — unlike the instructor endpoint, these filters
     * are not overridable.
     */
    public function index(GetCalendarItemsRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'Student profile not found for the authenticated user.',
            ], 404);
        }

        if (! $student->instructor_id) {
            return response()->json([
                'message' => 'You must be attached to an instructor before you can view available slots.',
            ], 422);
        }

        $items = $this->calendarService->getCalendarItems(
            $student->instructor,
            $request->validated('date'),
            availableOnly: true,
            excludeDrafts: true
        );

        return CalendarItemResource::collection($items);
    }
}
