<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Student;
use App\Notifications\LessonPaymentReceivedNotification;
use App\Notifications\OrderConfirmationNotification;
use App\Notifications\PaymentDueSoonNotification;
use App\Notifications\PaymentLinkNotification;
use Illuminate\Notifications\AnonymousNotifiable;

beforeEach(function () {
    $this->instructor = Instructor::factory()->create();
    $this->student = Student::factory()->create(['instructor_id' => $this->instructor->id]);
    $this->notifiable = (new AnonymousNotifiable)->route('mail', $this->student->email);
});

function feeOrderFor(Student $student, Instructor $instructor, PaymentMode $paymentMode): Order
{
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'package_name' => '10 Lesson Package',
        'package_total_price_pence' => 60000,
        'package_lesson_price_pence' => 6000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 65989,
        'payment_mode' => $paymentMode,
        'status' => $paymentMode === PaymentMode::UPFRONT ? OrderStatus::PENDING : OrderStatus::ACTIVE,
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'amount_pence' => 6000,
        'date' => now()->addDays(3)->toDateString(),
    ]);

    return $order;
}

function weeklyFeePayment(Order $order): LessonPayment
{
    return LessonPayment::factory()->create([
        'lesson_id' => $order->lessons()->first()->id,
        'amount_pence' => 6599,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addDays(2),
    ]);
}

it('itemises fees in the payment link email sent for instructor bookings', function () {
    $order = feeOrderFor($this->student, $this->instructor, PaymentMode::UPFRONT);

    $html = (string) (new PaymentLinkNotification($order, $this->student, 'https://checkout.test', false))
        ->toMail($this->notifiable)
        ->render();

    expect($html)->toContain('Lessons: £600.00')
        ->and($html)->toContain('Booking fee: £19.99')
        ->and($html)->toContain('Digital fee: £39.90')
        ->and($html)->toContain('£659.89')
        ->and($html)->not->toContain('{{cost_breakdown}}');
});

it('shows the fee-inclusive weekly instalment in the weekly order confirmation', function () {
    $order = feeOrderFor($this->student, $this->instructor, PaymentMode::WEEKLY);

    $html = (string) (new OrderConfirmationNotification($order, $this->student, false))
        ->toMail($this->notifiable)
        ->render();

    expect($html)->toContain('Booking fee: £19.99')
        ->and($html)->toContain('Digital fee: £39.90')
        ->and($html)->toContain('Total: £659.89')
        ->and($html)->toContain('10 weekly instalments of £65.99')
        ->and($html)->not->toContain('£60.00 per lesson');
});

it('itemises fees in the payment due soon email', function () {
    $payment = weeklyFeePayment(feeOrderFor($this->student, $this->instructor, PaymentMode::WEEKLY));

    $html = (string) (new PaymentDueSoonNotification($payment, $this->student, 'https://invoice.test', false))
        ->toMail($this->notifiable)
        ->render();

    expect($html)->toContain('Booking fee (weekly instalment)')
        ->and($html)->toContain('Digital services fee (weekly instalment)')
        ->and($html)->toContain('£65.99');
});

it('itemises fees in the payment confirmed email', function () {
    $payment = weeklyFeePayment(feeOrderFor($this->student, $this->instructor, PaymentMode::WEEKLY));

    $html = (string) (new LessonPaymentReceivedNotification($payment, $this->student, false))
        ->toMail($this->notifiable)
        ->render();

    expect($html)->toContain('Booking fee (weekly instalment)')
        ->and($html)->toContain('Digital services fee (weekly instalment)')
        ->and($html)->toContain('£65.99');
});
