<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetCalendarItemsRequest;
use App\Http\Resources\V1\CalendarItemResource;
use App\Services\InstructorCalendarService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorCalendarController extends Controller
{
    public function __construct(
        protected InstructorCalendarService $calendarService
    ) {}

    /**
     * Return available calendar items for the authenticated instructor on a given date.
     */
    public function index(GetCalendarItemsRequest $request): AnonymousResourceCollection
    {
        $instructor = $request->user()->instructor;

        $items = $this->calendarService->getCalendarItems(
            $instructor,
            $request->validated('date')
        );

        return CalendarItemResource::collection($items);
    }
}
