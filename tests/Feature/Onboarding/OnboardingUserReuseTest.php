<?php

use App\Actions\Onboarding\CancelPendingOrderAction;
use App\Actions\Onboarding\CreateUserAndStudentFromEnquiryAction;
use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;

beforeEach(function () {
    $this->instructor = Instructor::factory()->create();
});

function createEnquiry(int $instructorId, string $email = 'student@example.com'): Enquiry
{
    return Enquiry::create([
        'data' => [
            'current_step' => 5,
            'steps' => [
                'step1' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => $email,
                    'phone' => '07700900000',
                    'postcode' => 'SW1A 1AA',
                    'privacy_consent' => true,
                ],
                'step2' => [
                    'instructor_id' => $instructorId,
                ],
                'step5' => [],
            ],
        ],
        'current_step' => 5,
        'max_step_reached' => 5,
    ]);
}

test('creates new user when email does not exist', function () {
    $enquiry = createEnquiry($this->instructor->id);

    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldReceive('createOrGetCustomer')
        ->once()
        ->andReturn(['success' => true, 'customer_id' => 'cus_test123']);

    $action = new CreateUserAndStudentFromEnquiryAction($stripeService);
    $result = $action->execute($enquiry);

    expect($result['is_new_user'])->toBeTrue();
    expect($result['user']->email)->toBe('student@example.com');
    expect($result['user']->role)->toBe(UserRole::STUDENT);
    expect($result['student']->first_name)->toBe('John');
    expect($result['student']->surname)->toBe('Doe');
    expect(User::where('email', 'student@example.com')->count())->toBe(1);
});

test('reuses existing user when email already exists', function () {
    $existingUser = User::factory()->create([
        'email' => 'student@example.com',
        'role' => UserRole::STUDENT,
        'stripe_customer_id' => 'cus_existing123',
    ]);

    $enquiry = createEnquiry($this->instructor->id, 'student@example.com');

    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldNotReceive('createOrGetCustomer');

    $action = new CreateUserAndStudentFromEnquiryAction($stripeService);
    $result = $action->execute($enquiry);

    expect($result['is_new_user'])->toBeFalse();
    expect($result['user']->id)->toBe($existingUser->id);
    expect(User::where('email', 'student@example.com')->count())->toBe(1);
});

test('updates existing student data on repeated onboarding', function () {
    $existingUser = User::factory()->create([
        'email' => 'student@example.com',
        'role' => UserRole::STUDENT,
        'stripe_customer_id' => 'cus_existing123',
    ]);

    $existingStudent = Student::factory()->create([
        'user_id' => $existingUser->id,
        'instructor_id' => $this->instructor->id,
        'first_name' => 'OldFirst',
        'surname' => 'OldLast',
    ]);

    $enquiry = createEnquiry($this->instructor->id, 'student@example.com');

    $stripeService = Mockery::mock(StripeService::class);
    $action = new CreateUserAndStudentFromEnquiryAction($stripeService);
    $result = $action->execute($enquiry);

    expect($result['student']->id)->toBe($existingStudent->id);
    expect($result['student']->fresh()->first_name)->toBe('John');
    expect($result['student']->fresh()->surname)->toBe('Doe');
    expect(Student::where('user_id', $existingUser->id)->count())->toBe(1);
});

test('creates stripe customer for existing user without one', function () {
    $existingUser = User::factory()->create([
        'email' => 'student@example.com',
        'role' => UserRole::STUDENT,
        'stripe_customer_id' => null,
    ]);

    $enquiry = createEnquiry($this->instructor->id, 'student@example.com');

    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldReceive('createOrGetCustomer')
        ->once()
        ->andReturn(['success' => true, 'customer_id' => 'cus_new123']);

    $action = new CreateUserAndStudentFromEnquiryAction($stripeService);
    $result = $action->execute($enquiry);

    expect($result['user']->fresh()->stripe_customer_id)->toBe('cus_new123');
});

test('cancel pending order action releases calendar items', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_mode' => PaymentMode::WEEKLY,
    ]);

    $calendarItems = CalendarItem::factory()->count(2)->create([
        'status' => CalendarItemStatus::RESERVED,
        'is_available' => false,
    ]);

    foreach ($calendarItems as $i => $calendarItem) {
        Lesson::factory()->create([
            'order_id' => $order->id,
            'calendar_item_id' => $calendarItem->id,
            'status' => LessonStatus::PENDING,
        ]);
    }

    $action = new CancelPendingOrderAction;
    ($action)($order);

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED);
    expect($order->lessons()->count())->toBe(0);

    foreach ($calendarItems as $calendarItem) {
        $fresh = $calendarItem->fresh();
        expect($fresh->status)->toBe(CalendarItemStatus::DRAFT);
        expect($fresh->is_available)->toBeTrue();
    }
});

test('cancel pending order action skips non-pending orders', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::ACTIVE,
    ]);

    $action = new CancelPendingOrderAction;
    ($action)($order);

    expect($order->fresh()->status)->toBe(OrderStatus::ACTIVE);
});
