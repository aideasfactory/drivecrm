<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\LessonPayment;
use App\Support\BookingPayments;
use Carbon\Carbon;

class UpdateCalendarItemAction
{
    /**
     * Update a calendar item (time slot) - handles moves and status changes.
     *
     * @param  Instructor  $instructor  The instructor who owns the calendar
     * @param  CalendarItem  $calendarItem  The calendar item to update
     * @param  string  $date  New date in Y-m-d format
     * @param  string  $startTime  New start time in H:i format
     * @param  string  $endTime  New end time in H:i format
     * @param  bool|null  $isAvailable  New availability status (null to keep unchanged)
     * @param  string|null  $notes  Notes about the slot (null to keep unchanged)
     * @param  string|null  $unavailabilityReason  Reason for unavailability (null to keep unchanged)
     * @param  int|null  $travelTimeMinutes  Travel time in minutes (0, 15, 30, or 45). Pass 0 to remove travel block.
     * @return CalendarItem The updated calendar item
     */
    public function __invoke(
        Instructor $instructor,
        CalendarItem $calendarItem,
        string $date,
        string $startTime,
        string $endTime,
        ?bool $isAvailable = null,
        ?string $notes = null,
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null
    ): CalendarItem {
        $oldCalendar = $calendarItem->calendar;
        $newDate = Carbon::parse($date)->format('Y-m-d');
        $isMoved = ! $calendarItem->isAt($newDate, $startTime, $endTime);

        // Check if date changed - need to move to different calendar
        if ($oldCalendar->date->format('Y-m-d') !== $newDate) {
            // Find or create calendar for the new date
            $newCalendar = Calendar::firstOrCreate([
                'instructor_id' => $instructor->id,
                'date' => $newDate,
            ]);

            $calendarItem->calendar_id = $newCalendar->id;

            // Clean up old calendar if it has no more items
            if ($oldCalendar->items()->where('id', '!=', $calendarItem->id)->count() === 0) {
                $oldCalendar->delete();
            }
        }

        // Update times
        $calendarItem->start_time = $startTime;
        $calendarItem->end_time = $endTime;

        // Update availability if provided
        if ($isAvailable !== null) {
            $calendarItem->is_available = $isAvailable;
        }

        // Update notes if provided
        if ($notes !== null) {
            $calendarItem->notes = $notes;
        }

        // Update unavailability reason if provided
        if ($unavailabilityReason !== null) {
            $calendarItem->unavailability_reason = $unavailabilityReason;
        }

        // Update travel time minutes if provided
        if ($travelTimeMinutes !== null) {
            $calendarItem->travel_time_minutes = $travelTimeMinutes > 0 ? $travelTimeMinutes : null;
        }

        $calendarItem->save();

        // A booked lesson may land on an empty availability slot — take that
        // slot over by deleting it (and its travel block) instead of clashing.
        $this->consumeOverlappedAvailability($calendarItem, $startTime, $endTime);

        // Sync linked lessons when the calendar item is moved
        $this->syncLessonDates($calendarItem, $newDate, $startTime, $endTime, $isMoved);

        // Handle travel-time block changes
        $this->syncTravelBlock($calendarItem, $travelTimeMinutes);

        $calendarItem->load('calendar');

        return $calendarItem;
    }

    /**
     * When a booked item is moved on top of empty availability, consume the
     * overlapped slot(s): delete each open, unbooked slot (and its travel
     * block) whose window intersects the booking's new window. Anything else
     * overlapping was already rejected by request validation.
     */
    private function consumeOverlappedAvailability(CalendarItem $calendarItem, string $startTime, string $endTime): void
    {
        if (! $calendarItem->lessons()->exists()) {
            return;
        }

        $overlapping = CalendarItem::query()
            ->where('calendar_id', $calendarItem->calendar_id)
            ->where('id', '!=', $calendarItem->id)
            ->where(function ($query) use ($calendarItem): void {
                $query->whereNull('parent_item_id')
                    ->orWhere('parent_item_id', '!=', $calendarItem->id);
            })
            ->whereRaw('TIME(?) < TIME(end_time)', [$startTime])
            ->whereRaw('TIME(?) > TIME(start_time)', [$endTime])
            ->with('parentItem')
            ->get();

        $consumedSlots = collect();

        foreach ($overlapping as $item) {
            if ($item->isTravel() && $item->parentItem?->isEmptyAvailability()) {
                $consumedSlots->put($item->parentItem->id, $item->parentItem);
            } elseif ($item->isEmptyAvailability()) {
                $consumedSlots->put($item->id, $item);
            }
        }

        foreach ($consumedSlots as $slot) {
            $slot->travelItem?->delete();
            $slot->delete();
        }
    }

    /**
     * Update linked lessons' date and times to match the moved calendar item.
     * Moving a provisional (clashing) lesson resolves it, so the flag is cleared.
     */
    private function syncLessonDates(CalendarItem $calendarItem, string $date, string $startTime, string $endTime, bool $isMoved): void
    {
        $calendarItem->lessons()->update([
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            ...($isMoved ? ['provisional_at' => null] : []),
        ]);

        if ($isMoved) {
            $this->syncWeeklyPaymentDueDates($calendarItem, $date, $startTime);
        }
    }

    /**
     * A moved weekly lesson that hasn't been invoiced yet is due relative to its
     * new time, so a lesson moved earlier is still invoiced (by the hourly
     * `lessons:send-invoices` run) in time to pay before it. Invoices already
     * sent are left as they are.
     *
     * A payment auto-cancelled for being long overdue is revived: moving the
     * lesson shows it is still happening, so it is invoiced afresh (its old
     * Stripe invoice is already void) against the new date.
     */
    private function syncWeeklyPaymentDueDates(CalendarItem $calendarItem, string $date, string $startTime): void
    {
        LessonPayment::query()
            ->whereIn('lesson_id', $calendarItem->lessons()->select('id'))
            ->where('status', PaymentStatus::DUE)
            ->whereNotNull('auto_cancelled_at')
            ->whereHas('lesson.order', fn ($query) => $query->where('payment_mode', PaymentMode::WEEKLY))
            ->update([
                'auto_cancelled_at' => null,
                'auto_cancellation_reason' => null,
                'stripe_invoice_id' => null,
            ]);

        LessonPayment::query()
            ->whereIn('lesson_id', $calendarItem->lessons()->select('id'))
            ->where('status', PaymentStatus::DUE)
            ->whereNull('stripe_invoice_id')
            ->whereHas('lesson.order', fn ($query) => $query->where('payment_mode', PaymentMode::WEEKLY))
            ->update(['due_date' => BookingPayments::weeklyPaymentDueDate($date, $startTime)]);
    }

    /**
     * Synchronise the associated travel-time block: create, update, or remove as needed.
     */
    private function syncTravelBlock(CalendarItem $calendarItem, ?int $travelTimeMinutes): void
    {
        $travelItem = $calendarItem->travelItem;
        $effectiveAvailable = $calendarItem->is_available;
        $effectiveMinutes = $travelTimeMinutes ?? $calendarItem->travel_time_minutes;
        $hasLesson = $calendarItem->lessons()->exists();

        // Remove travel block when travel time is explicitly set to 0, or when the slot
        // is genuinely unavailable (no lesson booked). Booked lessons set `is_available`
        // to false because the slot is reserved — those still need their travel block.
        if ($travelItem && ($travelTimeMinutes === 0 || (! $effectiveAvailable && ! $hasLesson))) {
            $travelItem->delete();

            return;
        }

        // Update existing travel block
        if ($travelItem && $effectiveMinutes && $effectiveMinutes > 0) {
            $travelStart = Carbon::parse($calendarItem->end_time);
            $travelEnd = $travelStart->copy()->addMinutes($effectiveMinutes);

            $travelItem->calendar_id = $calendarItem->calendar_id;
            $travelItem->start_time = $travelStart->format('H:i');
            $travelItem->end_time = $travelEnd->format('H:i');
            $travelItem->save();

            return;
        }

        // Create new travel block if travel time was added and slot is available
        if (! $travelItem && $travelTimeMinutes && $travelTimeMinutes > 0 && $effectiveAvailable) {
            $travelStart = Carbon::parse($calendarItem->end_time);
            $travelEnd = $travelStart->copy()->addMinutes($travelTimeMinutes);

            CalendarItem::create([
                'calendar_id' => $calendarItem->calendar_id,
                'start_time' => $travelStart->format('H:i'),
                'end_time' => $travelEnd->format('H:i'),
                'is_available' => false,
                'item_type' => CalendarItemType::Travel,
                'parent_item_id' => $calendarItem->id,
                'status' => null,
                'notes' => null,
                'unavailability_reason' => 'Travel time',
            ]);
        }
    }
}
