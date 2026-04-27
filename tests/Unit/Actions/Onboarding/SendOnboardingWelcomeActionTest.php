<?php

use App\Actions\Onboarding\SendOnboardingWelcomeAction;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\OnboardingWelcomeNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->action = new SendOnboardingWelcomeAction;
});

it('sends temporary password email to new onboarding user', function () {
    $user = User::factory()->create();

    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 6,
            'steps' => [
                'step6' => [
                    'user_id' => $user->id,
                    'is_new_user' => true,
                    'temporary_password' => Crypt::encryptString('TempPass123!'),
                    'payment_status' => 'completed',
                ],
            ],
        ],
        'current_step' => 6,
        'max_step_reached' => 6,
    ]);

    ($this->action)($enquiry);

    Notification::assertSentTo($user, OnboardingWelcomeNotification::class, function ($notification) {
        return $notification->temporaryPassword === 'TempPass123!';
    });

    // Verify temp password is cleared and marked as sent
    $enquiry->refresh();
    $step6 = $enquiry->getStepData(6);
    expect($step6['temp_password_sent'])->toBeTrue();
    expect($step6['temporary_password'])->toBeNull();
});

it('does not send email if user is not new', function () {
    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 6,
            'steps' => [
                'step6' => [
                    'user_id' => 1,
                    'is_new_user' => false,
                    'payment_status' => 'completed',
                ],
            ],
        ],
        'current_step' => 6,
        'max_step_reached' => 6,
    ]);

    ($this->action)($enquiry);

    Notification::assertNothingSent();
});

it('does not send email twice (double-send prevention)', function () {
    $user = User::factory()->create();

    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 6,
            'steps' => [
                'step6' => [
                    'user_id' => $user->id,
                    'is_new_user' => true,
                    'temporary_password' => Crypt::encryptString('TempPass123!'),
                    'temp_password_sent' => true,
                    'payment_status' => 'completed',
                ],
            ],
        ],
        'current_step' => 6,
        'max_step_reached' => 6,
    ]);

    ($this->action)($enquiry);

    Notification::assertNothingSent();
});

it('does not send email when temporary password is missing', function () {
    $user = User::factory()->create();

    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 6,
            'steps' => [
                'step6' => [
                    'user_id' => $user->id,
                    'is_new_user' => true,
                    'payment_status' => 'completed',
                ],
            ],
        ],
        'current_step' => 6,
        'max_step_reached' => 6,
    ]);

    ($this->action)($enquiry);

    Notification::assertNothingSent();
});
