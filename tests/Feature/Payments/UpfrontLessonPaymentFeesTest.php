<?php

declare(strict_types=1);

use App\Actions\Student\Payment\GetStudentPaymentsAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Student;

it('backfills upfront lesson payments with their share of the fee-inclusive total', function () {
    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'package_total_price_pence' => 60000,
        'package_lesson_price_pence' => 6000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 65989,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::ACTIVE,
        'stripe_payment_intent_id' => 'pi_test',
    ]);

    foreach (range(0, 9) as $week) {
        Lesson::factory()->create([
            'order_id' => $order->id,
            'instructor_id' => $instructor->id,
            'amount_pence' => 6000,
            'date' => now()->addWeeks($week)->toDateString(),
        ]);
    }

    app(GetStudentPaymentsAction::class)($student);

    $amounts = LessonPayment::query()
        ->whereIn('lesson_id', $order->lessons()->pluck('id'))
        ->pluck('amount_pence');

    expect($amounts)->toHaveCount(10)
        ->and($amounts->sum())->toBe(65989);
});
