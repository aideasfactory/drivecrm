<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Calendar\MoveLessonAndFutureSiblingsAction;
use App\Enums\RecurrencePattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteCalendarItemRequest;
use App\Http\Requests\Api\V1\GetCalendarItemsRequest;
use App\Http\Requests\Api\V1\StoreCalendarItemRequest;
use App\Http\Requests\Api\V1\UpdateCalendarItemRequest;
use App\Http\Resources\V1\CalendarItemResource;
use App\Models\CalendarItem;
use App\Services\InstructorCalendarService;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorCalendarController extends Controller
{
    public function __construct(
        protected InstructorCalendarService $calendarService,
        protected InstructorService $instructorService,
        protected MoveLessonAndFutureSiblingsAction $moveLessonAndFutureSiblings,
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
        $excludeDrafts = $request->boolean('exclude_drafts', true);

        $items = $this->calendarService->getCalendarItems(
            $instructor,
            $request->validated('date'),
            $availableOnly,
            $excludeDrafts
        );

        // Eager-load the booking context the resource exposes (student name, paid
        // status, future-sibling count) so the app can drive the status-dependent
        // edit UI. Cheap for available-only requests (those slots have no lessons).
        $items->loadMissing([
            'calendar',
            'lessons.order.student',
            'lessons.order.lessons.payout',
            'lessons.lessonPayment',
        ]);

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
        $studentId = $request->integer('student_id') ?: null;

        $calendarItem = $this->instructorService->addCalendarItem(
            $instructor,
            $request->input('date'),
            $request->input('start_time'),
            $request->input('end_time'),
            $request->boolean('is_available', true),
            $request->input('notes'),
            $request->input('unavailability_reason'),
            $travelTimeMinutes,
            $isPracticalTest,
            $studentId
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
     * Update a calendar item for the authenticated instructor — move, edit, or reschedule.
     *
     * Mirrors the admin schedule edit flow. With apply_to_future_in_order=true the whole
     * booking is bulk-rescheduled (the lesson plus every future un-signed-off lesson in the
     * same order), otherwise a single slot/lesson is moved or edited. Both paths reuse the
     * same Service/Action layer as the web UI, so reschedule notifications fire identically.
     */
    public function update(UpdateCalendarItemRequest $request, CalendarItem $calendarItem): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if ($calendarItem->calendar->instructor_id !== $instructor->id) {
            return response()->json([
                'message' => 'Calendar item not found.',
            ], 404);
        }

        // Bulk-mode: move this lesson AND every future un-signed-off lesson in the same order
        // to the same day-of-week + time, weekly cadence anchored on the new date.
        if ($request->boolean('apply_to_future_in_order')) {
            $result = ($this->moveLessonAndFutureSiblings)(
                $instructor,
                $calendarItem,
                $request->input('date'),
                $request->input('start_time'),
                $request->input('end_time'),
                $request->user(),
            );

            return (new CalendarItemResource($result['anchor_item']))
                ->additional([
                    'mode' => 'bulk',
                    'moved_count' => $result['moved_count'],
                ])
                ->response();
        }

        $calendarItem = $this->instructorService->updateCalendarItem(
            $instructor,
            $calendarItem,
            $request->input('date'),
            $request->input('start_time'),
            $request->input('end_time'),
            $request->has('is_available') ? $request->boolean('is_available') : null,
            $request->has('notes') ? $request->input('notes') : null,
            $request->has('unavailability_reason') ? $request->input('unavailability_reason') : null,
            $request->has('travel_time_minutes') ? $request->integer('travel_time_minutes') : null,
        );

        return (new CalendarItemResource($calendarItem))
            ->additional(['mode' => 'single'])
            ->response();
    }

    /**
     * Delete a calendar item for the authenticated instructor.
     *
     * Availability slots: ?scope=single (default) or ?scope=future for recurring
     * items. Booking slots (a lesson is attached): the request must include a
     * `reason`, and `scope=single` cancels just this lesson while `scope=future`
     * cancels this and all future un-signed-off lessons in the same booking.
     */
    public function destroy(DeleteCalendarItemRequest $request, CalendarItem $calendarItem): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if ($calendarItem->calendar->instructor_id !== $instructor->id) {
            return response()->json([
                'message' => 'Calendar item not found.',
            ], 404);
        }

        $deleteScope = $request->input('scope', 'single');

        try {
            // Booking slot: cancel the lesson(s) rather than delete an availability slot.
            if ($calendarItem->lessons()->exists()) {
                $result = $this->instructorService->cancelBooking(
                    $calendarItem,
                    (string) $request->input('reason'),
                    $deleteScope === 'future',
                    $request->user(),
                );

                return response()->json([
                    'message' => "{$result['cancelled_count']} lesson(s) cancelled. The student has been notified.",
                    'cancelled_count' => $result['cancelled_count'],
                    'refund_required_count' => $result['refund_required_count'],
                ]);
            }

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
