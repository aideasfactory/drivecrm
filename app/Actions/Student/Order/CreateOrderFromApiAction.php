<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateOrderFromApiAction
{
    /**
     * Create an order with scheduled lessons from API request data.
     *
     * @param  array<int, int>  $calendarItemIds  Draft calendar item IDs (one per lesson)
     */
    public function __invoke(
        Student $student,
        Package $package,
        PaymentMode $paymentMode,
        string $firstLessonDate,
        string $startTime,
        string $endTime,
        array $calendarItemIds
    ): Order {
        return DB::transaction(function () use ($student, $package, $paymentMode, $firstLessonDate, $startTime, $endTime, $calendarItemIds): Order {
            $instructorId = $student->instructor_id;

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

            Log::info('Created order from API', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'package_id' => $package->id,
                'payment_mode' => $paymentMode->value,
            ]);

            $calendarItemStatus = $paymentMode === PaymentMode::UPFRONT
                ? CalendarItemStatus::BOOKED
                : CalendarItemStatus::RESERVED;

            for ($i = 0; $i < $package->lessons_count; $i++) {
                $scheduledDate = Carbon::parse($firstLessonDate)->addWeeks($i);
                $calendarItemId = $calendarItemIds[$i] ?? null;

                if ($calendarItemId) {
                    CalendarItem::where('id', $calendarItemId)->update([
                        'status' => $calendarItemStatus,
                        'is_available' => false,
                    ]);
                }

                $lesson = Lesson::create([
                    'order_id' => $order->id,
                    'instructor_id' => $instructorId,
                    'amount_pence' => $package->lesson_price_pence,
                    'status' => LessonStatus::PENDING,
                    'date' => $scheduledDate->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'calendar_item_id' => $calendarItemId,
                ]);

                if ($paymentMode === PaymentMode::WEEKLY) {
                    LessonPayment::create([
                        'lesson_id' => $lesson->id,
                        'amount_pence' => $lesson->amount_pence,
                        'status' => PaymentStatus::DUE,
                        'due_date' => $scheduledDate->copy()->subHours(24),
                    ]);
                }
            }

            if ($paymentMode === PaymentMode::WEEKLY) {
                $order->status = OrderStatus::ACTIVE;
                $order->save();
            }

            Log::info('Order creation complete', [
                'order_id' => $order->id,
                'lessons_created' => $package->lessons_count,
                'status' => $order->status->value,
            ]);

            return $order;
        });
    }
}
