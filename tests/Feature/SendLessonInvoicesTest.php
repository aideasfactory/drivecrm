<?php

use App\Actions\Payment\SendLessonInvoiceAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
    $this->instructor = Instructor::factory()->stripeConnected()->create();
    $this->student = Student::factory()->create([
        'user_id' => $this->user->id,
        'instructor_id' => $this->instructor->id,
    ]);
    $this->package = Package::factory()->forInstructor($this->instructor)->create();
    $this->order = Order::factory()->create([
        'student_id' => $this->student->id,
        'instructor_id' => $this->instructor->id,
        'package_id' => $this->package->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);
});

it('sends invoices for lessons due within 48 hours', function () {
    $lesson = Lesson::factory()->create([
        'order_id' => $this->order->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(36),
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(36),
        'stripe_invoice_id' => null,
    ]);

    $mockAction = Mockery::mock(SendLessonInvoiceAction::class);
    $mockAction->shouldReceive('__invoke')
        ->once()
        ->andReturn(['success' => true, 'invoice_id' => 'in_test', 'hosted_invoice_url' => 'https://stripe.com/invoice/test']);

    $this->app->instance(SendLessonInvoiceAction::class, $mockAction);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('Found 1 lesson(s)')
        ->expectsOutputToContain('Invoice sent')
        ->assertExitCode(0);
});

it('skips lessons with due date beyond 48 hours', function () {
    $lesson = Lesson::factory()->create([
        'order_id' => $this->order->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addDays(5),
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addDays(5),
        'stripe_invoice_id' => null,
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('skips already paid lesson payments', function () {
    $lesson = Lesson::factory()->create([
        'order_id' => $this->order->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
    ]);

    LessonPayment::factory()->paid()->create([
        'lesson_id' => $lesson->id,
        'due_date' => now()->addHours(24),
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('skips lesson payments where invoice was already sent', function () {
    $lesson = Lesson::factory()->create([
        'order_id' => $this->order->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
    ]);

    LessonPayment::factory()->invoiceSent()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(24),
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('skips cancelled lessons', function () {
    $lesson = Lesson::factory()->create([
        'order_id' => $this->order->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
        'status' => LessonStatus::CANCELLED,
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(24),
        'stripe_invoice_id' => null,
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('skips upfront payment orders', function () {
    $upfrontOrder = Order::factory()->create([
        'student_id' => $this->student->id,
        'instructor_id' => $this->instructor->id,
        'package_id' => $this->package->id,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::ACTIVE,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $upfrontOrder->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(24),
        'stripe_invoice_id' => null,
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('skips cancelled orders', function () {
    $cancelledOrder = Order::factory()->create([
        'student_id' => $this->student->id,
        'instructor_id' => $this->instructor->id,
        'package_id' => $this->package->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::CANCELLED,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $cancelledOrder->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(24),
        'stripe_invoice_id' => null,
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('skips students without stripe customer id', function () {
    $userNoStripe = User::factory()->create(['stripe_customer_id' => null]);
    $studentNoStripe = Student::factory()->create([
        'user_id' => $userNoStripe->id,
        'instructor_id' => $this->instructor->id,
    ]);
    $orderNoStripe = Order::factory()->create([
        'student_id' => $studentNoStripe->id,
        'instructor_id' => $this->instructor->id,
        'package_id' => $this->package->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $orderNoStripe->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(24),
        'stripe_invoice_id' => null,
    ]);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('No invoices to send')
        ->assertExitCode(0);
});

it('returns exit code 1 when some invoices fail', function () {
    $lesson = Lesson::factory()->create([
        'order_id' => $this->order->id,
        'instructor_id' => $this->instructor->id,
        'date' => now()->addHours(24),
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'due_date' => now()->addHours(24),
        'stripe_invoice_id' => null,
    ]);

    $mockAction = Mockery::mock(SendLessonInvoiceAction::class);
    $mockAction->shouldReceive('__invoke')
        ->once()
        ->andReturn(['success' => false, 'error' => 'Stripe error']);

    $this->app->instance(SendLessonInvoiceAction::class, $mockAction);

    $this->artisan('lessons:send-invoices')
        ->expectsOutputToContain('Failed')
        ->assertExitCode(1);
});
