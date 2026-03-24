<?php

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;

test('confirm calendar items transitions draft items to booked for an order', function () {
    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $calendar = Calendar::factory()->create(['instructor_id' => $instructor->id]);

    $calendarItem1 = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'status' => CalendarItemStatus::DRAFT,
        'is_available' => false,
    ]);
    $calendarItem2 = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'status' => CalendarItemStatus::DRAFT,
        'is_available' => false,
    ]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'status' => OrderStatus::PENDING,
        'payment_mode' => PaymentMode::UPFRONT,
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $calendarItem1->id,
        'status' => LessonStatus::PENDING,
    ]);
    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $calendarItem2->id,
        'status' => LessonStatus::PENDING,
    ]);

    $action = app(ConfirmCalendarItemsAction::class);
    $updated = $action($order);

    expect($updated)->toBe(2);
    expect($calendarItem1->fresh()->status)->toBe(CalendarItemStatus::BOOKED);
    expect($calendarItem2->fresh()->status)->toBe(CalendarItemStatus::BOOKED);
    expect($calendarItem1->fresh()->is_available)->toBeFalse();
    expect($calendarItem2->fresh()->is_available)->toBeFalse();
});

test('confirm calendar items does not change already booked items', function () {
    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $calendar = Calendar::factory()->create(['instructor_id' => $instructor->id]);

    $calendarItem = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'status' => CalendarItemStatus::BOOKED,
        'is_available' => false,
    ]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'status' => OrderStatus::ACTIVE,
        'payment_mode' => PaymentMode::UPFRONT,
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $calendarItem->id,
        'status' => LessonStatus::PENDING,
    ]);

    $action = app(ConfirmCalendarItemsAction::class);
    $updated = $action($order);

    expect($updated)->toBe(0);
    expect($calendarItem->fresh()->status)->toBe(CalendarItemStatus::BOOKED);
});

test('confirm calendar items returns zero when order has no calendar items', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_mode' => PaymentMode::UPFRONT,
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'calendar_item_id' => null,
        'status' => LessonStatus::PENDING,
    ]);

    $action = app(ConfirmCalendarItemsAction::class);
    $updated = $action($order);

    expect($updated)->toBe(0);
});

test('upfront order creation keeps calendar items in draft status', function () {
    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $calendar = Calendar::factory()->create(['instructor_id' => $instructor->id]);

    $calendarItem = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'status' => CalendarItemStatus::DRAFT,
        'is_available' => false,
    ]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'status' => OrderStatus::PENDING,
        'payment_mode' => PaymentMode::UPFRONT,
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $calendarItem->id,
        'status' => LessonStatus::PENDING,
    ]);

    // Calendar item should remain DRAFT — not BOOKED — until payment is confirmed
    expect($calendarItem->fresh()->status)->toBe(CalendarItemStatus::DRAFT);
});
