<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Enquiry;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateOrderFromEnquiryAction
{
    /**
     * Create order with scheduled lessons from enquiry data.
     *
     * @throws \Exception
     */
    public function execute(
        Enquiry $enquiry,
        Student $student,
        Package $package,
        PaymentMode $paymentMode
    ): Order {
        try {
            DB::beginTransaction();

            $step2 = $enquiry->getStepData(2) ?? [];
            $step4 = $enquiry->getStepData(4) ?? [];

            // Get instructor ID from step 2 or use package instructor
            $instructorId = $step2['instructor_id'] ?? $package->instructor_id;

            // Create Order record with package snapshot
            $order = Order::create([
                'student_id' => $student->id,
                'instructor_id' => $instructorId,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_total_price_pence' => $package->total_price_pence,
                'package_lesson_price_pence' => $package->lesson_price_pence,
                'package_lessons_count' => $package->lessons_count,
                'status' => OrderStatus::PENDING,
                'payment_mode' => $paymentMode,
            ]);

            Log::info('Created order from onboarding', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'package_id' => $package->id,
                'payment_mode' => $paymentMode->value,
                'enquiry_id' => $enquiry->id,
            ]);

            // Get first lesson date and time from Step 4
            $date = $step4['date'] ?? null;
            $startTime = $step4['start_time'] ?? null;

            Log::info('Processing first lesson datetime from Step 4', [
                'date' => $date,
                'start_time' => $startTime,
                'step4_data' => $step4,
                'order_id' => $order->id,
            ]);

            if (! $date) {
                Log::error('First lesson date missing from Step 4', [
                    'enquiry_id' => $enquiry->id,
                    'step4_data' => $step4,
                ]);
                throw new \Exception('First lesson date is required from Step 4');
            }

            // Combine date and time into a proper datetime
            if ($startTime) {
                $firstLessonDate = Carbon::parse("$date $startTime");
                Log::info('Combined date and time for first lesson', [
                    'combined_datetime' => $firstLessonDate->toDateTimeString(),
                    'order_id' => $order->id,
                ]);
            } else {
                // Fallback: use date only (will default to 00:00:00)
                $firstLessonDate = Carbon::parse($date);
                Log::warning('Start time missing from Step 4, using date only', [
                    'date_only' => $firstLessonDate->toDateString(),
                    'order_id' => $order->id,
                ]);
            }

            // Get time details and calendar items from Step 4
            $startTime = $step4['start_time'] ?? null;
            $endTime = $step4['end_time'] ?? null;
            $calendarItemIds = $step4['calendar_item_ids'] ?? [];

            Log::info('Retrieved calendar items from Step 4', [
                'calendar_item_ids' => $calendarItemIds,
                'calendar_items_count' => count($calendarItemIds),
                'order_id' => $order->id,
            ]);

            // Create scheduled lessons and update calendar items
            $this->createScheduledLessons(
                $order,
                $package,
                $instructorId,
                $firstLessonDate,
                $paymentMode,
                $startTime,
                $endTime,
                $calendarItemIds
            );

            // For weekly payment mode, create lesson payment records
            if ($paymentMode === PaymentMode::WEEKLY) {
                $this->createLessonPayments($order);
            }

            DB::commit();

            Log::info('Successfully created order and lessons from onboarding', [
                'order_id' => $order->id,
                'lessons_count' => $package->lessons_count,
                'first_lesson_date' => $firstLessonDate->toDateString(),
                'payment_mode' => $paymentMode->value,
            ]);

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create order from enquiry', [
                'enquiry_id' => $enquiry->id,
                'package_id' => $package->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create scheduled lessons for the order and update calendar items.
     */
    protected function createScheduledLessons(
        Order $order,
        Package $package,
        ?int $instructorId,
        Carbon $firstLessonDate,
        PaymentMode $paymentMode,
        ?string $startTime = null,
        ?string $endTime = null,
        array $calendarItemIds = []
    ): void {
        Log::info('Creating scheduled lessons and updating calendar items', [
            'order_id' => $order->id,
            'lessons_count' => $package->lessons_count,
            'first_lesson_date' => $firstLessonDate->toDateTimeString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'calendar_item_ids' => $calendarItemIds,
            'calendar_items_count' => count($calendarItemIds),
            'payment_mode' => $paymentMode->value,
        ]);

        // Determine calendar item status based on payment mode
        $calendarItemStatus = $paymentMode === PaymentMode::UPFRONT
            ? CalendarItemStatus::BOOKED
            : CalendarItemStatus::RESERVED;

        Log::info('Calendar items will be updated to status', [
            'status' => $calendarItemStatus,
            'payment_mode' => $paymentMode->value,
            'order_id' => $order->id,
        ]);

        for ($i = 0; $i < $package->lessons_count; $i++) {
            // Schedule each lesson one week apart
            $scheduledDate = $firstLessonDate->copy()->addWeeks($i);

            // Get the corresponding calendar item ID
            $calendarItemId = $calendarItemIds[$i] ?? null;

            if (! $calendarItemId) {
                Log::error('Calendar item ID missing for lesson', [
                    'lesson_number' => $i + 1,
                    'expected_calendar_item_ids' => count($calendarItemIds),
                    'lessons_count' => $package->lessons_count,
                    'order_id' => $order->id,
                ]);
                throw new \Exception('Calendar item ID missing for lesson #'.($i + 1));
            }

            // Get and update the calendar item
            $calendarItem = CalendarItem::find($calendarItemId);

            if (! $calendarItem) {
                Log::error('Calendar item not found', [
                    'calendar_item_id' => $calendarItemId,
                    'lesson_number' => $i + 1,
                    'order_id' => $order->id,
                ]);
                throw new \Exception("Calendar item not found: {$calendarItemId}");
            }

            // Update calendar item status (from draft to booked/reserved)
            $calendarItem->update([
                'status' => $calendarItemStatus,
                'is_available' => false, // Keep unavailable
            ]);

            Log::info('Updated calendar item status', [
                'calendar_item_id' => $calendarItem->id,
                'old_status' => 'draft',
                'new_status' => $calendarItemStatus,
                'is_available' => false,
                'lesson_number' => $i + 1,
                'order_id' => $order->id,
            ]);

            // Create lesson linked to the calendar item
            $lessonData = [
                'order_id' => $order->id,
                'instructor_id' => $instructorId,
                'amount_pence' => $package->lesson_price_pence,
                'status' => LessonStatus::PENDING,
                'date' => $scheduledDate->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'calendar_item_id' => $calendarItem->id,
            ];

            $lesson = Lesson::create($lessonData);

            Log::info('Created lesson linked to updated calendar item', [
                'lesson_id' => $lesson->id,
                'lesson_number' => $i + 1,
                'date' => $lessonData['date'],
                'start_time' => $lessonData['start_time'],
                'end_time' => $lessonData['end_time'],
                'calendar_item_id' => $lessonData['calendar_item_id'],
                'calendar_item_status' => $calendarItemStatus,
                'order_id' => $order->id,
            ]);
        }

        Log::info('Created scheduled lessons and updated calendar items', [
            'order_id' => $order->id,
            'lessons_count' => $package->lessons_count,
            'calendar_items_updated' => count($calendarItemIds),
            'calendar_item_status' => $calendarItemStatus,
            'first_lesson_date' => $firstLessonDate->toDateString(),
        ]);
    }

    /**
     * Create lesson payment records for weekly payment mode.
     */
    protected function createLessonPayments(Order $order): void
    {
        $lessons = $order->lessons()->orderBy('date')->get();

        foreach ($lessons as $lesson) {
            $lessonDate = Carbon::parse($lesson->date);

            LessonPayment::create([
                'lesson_id' => $lesson->id,
                'amount_pence' => $lesson->amount_pence,
                'status' => PaymentStatus::DUE,
                'due_date' => $lessonDate->copy()->subHours(24), // Due 24h before lesson
            ]);
        }

        Log::info('Created lesson payments for weekly order', [
            'order_id' => $order->id,
            'payments_count' => $lessons->count(),
        ]);
    }
}
