<?php

use App\Actions\Payment\SendLessonInvoiceAction;
use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->stripeService = Mockery::mock(StripeService::class);
    $this->action = new SendLessonInvoiceAction($this->stripeService);
});

it('creates stripe invoice and updates lesson payment', function () {
    Notification::fake();

    $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['student_id' => $student->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id]);
    $lessonPayment = LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'stripe_invoice_id' => null,
    ]);

    $this->stripeService->shouldReceive('createInvoice')
        ->once()
        ->with($lesson, $user)
        ->andReturn([
            'success' => true,
            'invoice_id' => 'in_test123',
            'hosted_invoice_url' => 'https://stripe.com/invoice/test',
        ]);

    $result = ($this->action)($lessonPayment);

    expect($result['success'])->toBeTrue();
    expect($result['invoice_id'])->toBe('in_test123');
    expect($lessonPayment->fresh()->stripe_invoice_id)->toBe('in_test123');
});

it('sends payment reminder notification after invoice creation', function () {
    Notification::fake();

    $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'owns_account' => true,
        'email' => 'student@test.com',
    ]);
    $order = Order::factory()->create(['student_id' => $student->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id]);
    $lessonPayment = LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'stripe_invoice_id' => null,
    ]);

    $this->stripeService->shouldReceive('createInvoice')
        ->once()
        ->andReturn([
            'success' => true,
            'invoice_id' => 'in_test123',
            'hosted_invoice_url' => 'https://stripe.com/invoice/test',
        ]);

    ($this->action)($lessonPayment);

    Notification::assertCount(1);
});

it('returns error when student has no stripe customer id', function () {
    $user = User::factory()->create(['stripe_customer_id' => null]);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['student_id' => $student->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id]);
    $lessonPayment = LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'stripe_invoice_id' => null,
    ]);

    $this->stripeService->shouldNotReceive('createInvoice');

    $result = ($this->action)($lessonPayment);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Stripe customer ID');
});

it('returns error when stripe invoice creation fails', function () {
    Notification::fake();

    $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['student_id' => $student->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id]);
    $lessonPayment = LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
        'stripe_invoice_id' => null,
    ]);

    $this->stripeService->shouldReceive('createInvoice')
        ->once()
        ->andReturn(['success' => false, 'error' => 'Stripe API error']);

    $result = ($this->action)($lessonPayment);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Stripe API error');
    expect($lessonPayment->fresh()->stripe_invoice_id)->toBeNull();

    Notification::assertNothingSent();
});
