<?php

use App\Actions\Onboarding\CreateUserAndStudentFromEnquiryAction;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->stripeService = Mockery::mock(StripeService::class);
    $this->action = new CreateUserAndStudentFromEnquiryAction($this->stripeService);
});

it('returns temporary password for new users', function () {
    $instructor = Instructor::factory()->create();

    $this->stripeService->shouldReceive('createOrGetCustomer')
        ->once()
        ->andReturn(['success' => true, 'customer_id' => 'cus_test123']);

    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 5,
            'steps' => [
                'step1' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe.new@example.com',
                    'phone' => '07700900000',
                    'privacy_consent' => true,
                ],
                'step2' => ['instructor_id' => $instructor->id],
                'step5' => ['booking_for_someone_else' => false],
            ],
        ],
        'current_step' => 5,
        'max_step_reached' => 5,
    ]);

    $result = $this->action->execute($enquiry);

    expect($result['is_new_user'])->toBeTrue();
    expect($result['temporary_password'])->toBeString();
    expect($result['temporary_password'])->toHaveLength(12);
    expect(Hash::check($result['temporary_password'], $result['user']->password))->toBeTrue();
});

it('returns null temporary password for existing users', function () {
    $instructor = Instructor::factory()->create();
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 5,
            'steps' => [
                'step1' => [
                    'first_name' => 'Existing',
                    'last_name' => 'User',
                    'email' => 'existing@example.com',
                    'phone' => '07700900000',
                    'privacy_consent' => true,
                ],
                'step2' => ['instructor_id' => $instructor->id],
                'step5' => ['booking_for_someone_else' => false],
            ],
        ],
        'current_step' => 5,
        'max_step_reached' => 5,
    ]);

    $result = $this->action->execute($enquiry);

    expect($result['is_new_user'])->toBeFalse();
    expect($result['temporary_password'])->toBeNull();
});
