<?php

use App\Actions\Onboarding\CreateOrderFromEnquiryAction;
use App\Actions\Onboarding\CreateUserAndStudentFromEnquiryAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StripeService;

/**
 * Build an enquiry that has reached step 6 with a pending upfront order ready to be created.
 *
 * @return array{enquiry: Enquiry, user: User, student: Student, order: Order}
 */
function staffBookingFixture(bool $isStaffBooking): array
{
    $instructor = Instructor::factory()->stripeConnected()->create();
    $package = Package::factory()->forInstructor($instructor)->create([
        'stripe_price_id' => 'price_test123',
    ]);
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
        'stripe_customer_id' => 'cus_test123',
    ]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'email' => 'learner@example.com',
        'owns_account' => true,
    ]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'package_id' => $package->id,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::PENDING,
    ]);

    $data = [
        'current_step' => 5,
        'steps' => [
            'step1' => ['first_name' => 'Lara', 'last_name' => 'Learner', 'email' => 'learner@example.com'],
            'step2' => ['instructor_id' => $instructor->id],
            'step3' => ['package_id' => $package->id],
        ],
    ];

    if ($isStaffBooking) {
        $data['staff_booking'] = ['user_id' => 1, 'name' => 'Bookings Team'];
    }

    $enquiry = Enquiry::create([
        'data' => $data,
        'current_step' => 5,
        'max_step_reached' => 6,
    ]);

    $createUserAndStudent = Mockery::mock(CreateUserAndStudentFromEnquiryAction::class);
    $createUserAndStudent->shouldReceive('execute')
        ->andReturn(['user' => $user, 'student' => $student, 'is_new_user' => false]);
    app()->instance(CreateUserAndStudentFromEnquiryAction::class, $createUserAndStudent);

    $createOrder = Mockery::mock(CreateOrderFromEnquiryAction::class);
    $createOrder->shouldReceive('execute')->andReturn($order);
    app()->instance(CreateOrderFromEnquiryAction::class, $createOrder);

    return compact('enquiry', 'user', 'student', 'order');
}

test('owners starting onboarding with staff_booking flag the enquiry as a staff booking', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER, 'name' => 'Bookings Team']);

    $this->actingAs($owner)
        ->get(route('onboarding.start', ['staff_booking' => 1]))
        ->assertRedirect();

    $enquiry = Enquiry::query()->latest()->first();

    expect($enquiry->isStaffBooking())->toBeTrue()
        ->and($enquiry->getStaffBooking())->toBe(['user_id' => $owner->id, 'name' => 'Bookings Team']);
});

test('guests cannot start a staff booking', function () {
    $this->get(route('onboarding.start', ['staff_booking' => 1]))->assertRedirect();

    expect(Enquiry::query()->latest()->first()->isStaffBooking())->toBeFalse();
});

test('instructors cannot start a staff booking', function () {
    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);

    $this->actingAs($instructorUser)
        ->get(route('onboarding.start', ['staff_booking' => 1]))
        ->assertRedirect();

    expect(Enquiry::query()->latest()->first()->isStaffBooking())->toBeFalse();
});

test('staff upfront bookings email the payment link instead of redirecting to stripe', function () {
    ['enquiry' => $enquiry, 'student' => $student, 'order' => $order] = staffBookingFixture(isStaffBooking: true);

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('sendPaymentLink')
        ->once()
        ->withArgs(fn (Order $sentOrder, Student $sentStudent, string $source, bool $isBookedByStaff) => $sentOrder->is($order)
            && $sentStudent->is($student)
            && $source === 'staff_onboarding'
            && $isBookedByStaff === true)
        ->andReturn(['email' => 'learner@example.com']);
    $this->app->instance(OrderService::class, $orderService);

    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldNotReceive('createCheckoutSession');
    $this->app->instance(StripeService::class, $stripeService);

    $this->post(route('onboarding.step6.store', ['uuid' => $enquiry->id]), ['payment_mode' => 'upfront'])
        ->assertRedirect(route('onboarding.complete', ['uuid' => $enquiry->id]));

    $step6 = $enquiry->fresh()->getStepData(6);

    expect($step6['payment_status'])->toBe('awaiting_payment')
        ->and($step6['payment_link_sent_to'])->toBe('learner@example.com')
        ->and($order->fresh()->status)->toBe(OrderStatus::PENDING);
});

test('customer upfront bookings still redirect to stripe checkout', function () {
    ['enquiry' => $enquiry] = staffBookingFixture(isStaffBooking: false);

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldNotReceive('sendPaymentLink');
    $this->app->instance(OrderService::class, $orderService);

    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn(['success' => true, 'session_id' => 'cs_test123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test123']);
    $this->app->instance(StripeService::class, $stripeService);

    $this->post(route('onboarding.step6.store', ['uuid' => $enquiry->id]), ['payment_mode' => 'upfront'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test123');
});

test('the completion page tells staff where the payment link was sent', function () {
    $enquiry = Enquiry::create([
        'data' => [
            'staff_booking' => ['user_id' => 1, 'name' => 'Bookings Team'],
            'steps' => [
                'step6' => [
                    'payment_mode' => 'upfront',
                    'payment_status' => 'awaiting_payment',
                    'payment_link_sent_to' => 'learner@example.com',
                ],
            ],
        ],
        'current_step' => 6,
        'max_step_reached' => 6,
    ]);

    $this->get(route('onboarding.complete', ['uuid' => $enquiry->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Complete')
            ->where('staffBooking.payment_mode', 'upfront')
            ->where('staffBooking.payment_link_sent_to', 'learner@example.com')
        );
});
