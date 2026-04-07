<?php

declare(strict_types=1);

use App\Actions\Student\Lesson\SignOffLessonAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Student;

/*
|--------------------------------------------------------------------------
| SignOffLessonAction — Payment Guard Tests
|--------------------------------------------------------------------------
|
| Ensures that lessons cannot be signed off unless the individual lesson
| has been paid for, regardless of payment mode (upfront or weekly).
|
*/

test('sign-off is blocked for weekly lesson with unpaid payment', function () {
    $instructor = Instructor::factory()->create([
        'onboarding_complete' => true,
        'payouts_enabled' => true,
    ]);

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'status' => LessonStatus::PENDING,
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
    ]);

    $action = app(SignOffLessonAction::class);

    expect(fn () => $action($lesson, $instructor))
        ->toThrow(Exception::class, 'Payment has not been received yet');
});

test('sign-off is blocked for upfront lesson when order is not active', function () {
    $instructor = Instructor::factory()->create([
        'onboarding_complete' => true,
        'payouts_enabled' => true,
    ]);

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::PENDING,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'status' => LessonStatus::PENDING,
    ]);

    $action = app(SignOffLessonAction::class);

    expect(fn () => $action($lesson, $instructor))
        ->toThrow(Exception::class, 'The order has not been paid for');
});
