<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\CalendarItem\ReleaseDraftCalendarItemsAction;
use App\Actions\Shared\LogActivityAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Enums\OrderStatus;
use App\Models\CalendarItem;
use App\Models\Order;
use App\Services\InstructorCalendarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseUnpaidOrderAction
{
    public function __construct(
        protected ReleaseDraftCalendarItemsAction $releaseDraftCalendarItems,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Cancel an order that was never paid: delete its draft lessons (and their
     * payment rows), put the diary slots back on offer and mark it cancelled.
     *
     * The caller must make sure nothing can still be paid (the Stripe session is
     * closed) before calling this. Returns false when the order is no longer
     * pending, e.g. the payment landed first.
     */
    public function __invoke(Order $order, string $reason): bool
    {
        $calendarItemIds = DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! $lockedOrder->isPending()) {
                return null;
            }

            $calendarItemIds = $lockedOrder->lessons()
                ->whereNotNull('calendar_item_id')
                ->pluck('calendar_item_id');

            $lockedOrder->lessons()->delete();

            ($this->releaseDraftCalendarItems)($calendarItemIds);

            $lockedOrder->update(['status' => OrderStatus::CANCELLED]);

            return $calendarItemIds;
        });

        if ($calendarItemIds === null) {
            return false;
        }

        $order->refresh();

        ($this->recalculateStudentLessonNumbers)($order->student_id);

        $this->invalidateCalendarCache($order, $calendarItemIds->all());
        $this->logRelease($order, $reason);

        Log::info('Released unpaid order', [
            'order_id' => $order->id,
            'reason' => $reason,
            'calendar_item_ids' => $calendarItemIds->all(),
        ]);

        return true;
    }

    /**
     * @param  array<int, int>  $calendarItemIds
     */
    protected function invalidateCalendarCache(Order $order, array $calendarItemIds): void
    {
        if (! $order->instructor_id || empty($calendarItemIds)) {
            return;
        }

        $dates = CalendarItem::whereIn('calendar_items.id', $calendarItemIds)
            ->join('calendars', 'calendar_items.calendar_id', '=', 'calendars.id')
            ->pluck('calendars.date')
            ->unique();

        $calendarService = app(InstructorCalendarService::class);

        foreach ($dates as $date) {
            $calendarService->invalidateCalendarCache($order->instructor_id, $date);
        }
    }

    protected function logRelease(Order $order, string $reason): void
    {
        $metadata = [
            'type' => 'booking_released_unpaid',
            'order_id' => $order->id,
            'reason' => $reason,
            'payment_mode' => $order->payment_mode?->value,
            'payment_hold_expires_at' => $order->payment_hold_expires_at?->toIso8601String(),
        ];

        try {
            if ($order->student) {
                ($this->logActivity)(
                    $order->student,
                    "Unpaid booking released: {$order->package_name} ({$order->package_lessons_count} lessons) — payment was not received in time",
                    'booking',
                    $metadata,
                    'Booking released — payment not received in time'
                );
            }

            if ($order->instructor) {
                $studentName = trim(($order->student?->first_name ?? '').' '.($order->student?->surname ?? ''));

                ($this->logActivity)(
                    $order->instructor,
                    "Unpaid booking released for {$studentName}: {$order->package_name} ({$order->package_lessons_count} lessons) — the diary slots are available again",
                    'booking',
                    $metadata,
                    "Booking for {$studentName} released — payment not received in time"
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to log unpaid booking release', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
