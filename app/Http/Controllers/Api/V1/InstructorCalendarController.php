<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecurrencePattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetCalendarItemsRequest;
use App\Http\Requests\Api\V1\StoreCalendarItemRequest;
use App\Http\Resources\V1\CalendarItemResource;
use App\Models\CalendarItem;
use App\Services\InstructorCalendarService;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorCalendarController extends Controller
{
    public function __construct(
        protected InstructorCalendarService $calendarService,
        protected InstructorService $instructorService
    ) {}

    /**
     * Return calendar items for the authenticated instructor on a given date.
     *
     * Supports ?available_only=true (default) to return only available slots,
     * or ?available_only=false to return all items for the day.
     */
    public function index(GetCalendarItemsRequest $request): AnonymousResourceCollection
    {
        $instructor = $request->user()->instructor;
        $availableOnly = $request->boolean('available_only', true);

        $items = $this->calendarService->getCalendarItems(
            $instructor,
            $request->validated('date'),
            $availableOnly
        );

        return CalendarItemResource::collection($items);
    }

    /**
     * Create a new calendar item for the authenticated instructor.
     */
    public function store(StoreCalendarItemRequest $request): JsonResponse
    {
        $instructor = $request->user()->instructor;
        $pattern = RecurrencePattern::tryFrom($request->input('recurrence_pattern', 'none')) ?? RecurrencePattern::None;

        if ($pattern !== RecurrencePattern::None) {
            $travelTimeMinutes = $request->integer('travel_time_minutes') ?: null;

            $items = $this->instructorService->addRecurringCalendarItems(
                $instructor,
                $request->input('date'),
                $request->input('start_time'),
                $request->input('end_time'),
                $pattern,
                $request->input('recurrence_end_date'),
                $request->boolean('is_available', true),
                $request->input('notes'),
                $request->input('unavailability_reason'),
                $travelTimeMinutes
            );

            $firstItem = $items->first();
            $firstItem->load('calendar');

            return response()->json([
                'data' => new CalendarItemResource($firstItem),
                'recurring_count' => $items->count(),
            ], 201);
        }

        $travelTimeMinutes = $request->integer('travel_time_minutes') ?: null;
        $isPracticalTest = $request->boolean('is_practical_test');

        $calendarItem = $this->instructorService->addCalendarItem(
            $instructor,
            $request->input('date'),
            $request->input('start_time'),
            $request->input('end_time'),
            $request->boolean('is_available', true),
            $request->input('notes'),
            $request->input('unavailability_reason'),
            $travelTimeMinutes,
            $isPracticalTest
        );

        $calendarItem->load('calendar');

        $response = [
            'data' => new CalendarItemResource($calendarItem),
        ];

        if ($travelTimeMinutes) {
            $response['has_travel_item'] = true;
        }

        return response()->json($response, 201);
    }

    /**
     * Delete a calendar item for the authenticated instructor.
     *
     * Supports ?scope=single (default) or ?scope=future for recurring items.
     */
    public function destroy(Request $request, CalendarItem $calendarItem): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if ($calendarItem->calendar->instructor_id !== $instructor->id) {
            return response()->json([
                'message' => 'Calendar item not found.',
            ], 404);
        }

        $deleteScope = $request->query('scope', 'single');

        try {
            if ($deleteScope === 'future' && $calendarItem->isRecurring()) {
                $deletedCount = $this->instructorService->removeRecurringCalendarItems($calendarItem);

                return response()->json([
                    'message' => "{$deletedCount} recurring calendar item(s) removed successfully.",
                    'deleted_count' => $deletedCount,
                ]);
            }

            $this->instructorService->removeCalendarItem($calendarItem);

            return response()->json([
                'message' => 'Calendar item removed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
