<?php

declare(strict_types=1);

use App\Enums\EmailTemplateKey;
use App\Mail\InstructorWelcomeMail;
use App\Mail\PupilPasswordResetMail;
use App\Models\EmailTemplate;
use App\Models\Instructor;
use App\Models\User;
use App\Notifications\WelcomeStudentNotification;
use App\Services\EmailTemplateService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    createEmailTemplateTestSchema();
});

test('sending uses catalog defaults when no override is stored', function () {
    $rendered = app(EmailTemplateService::class)->render(
        EmailTemplateKey::LearnerWelcome,
        [
            'recipient_name' => 'Sam',
            'instructor_name' => 'Alex',
            'app_name' => 'Drive',
            'email' => 'sam@example.com',
            'temporary_password' => 'secret-pass',
        ],
        'https://example.test/app',
    );

    expect($rendered->subject)->toBe('Welcome to Drive!')
        ->and($rendered->greeting)->toBe('Hello Sam!')
        ->and($rendered->body)->toContain('Alex has added you as a student on Drive.')
        ->and($rendered->body)->toContain('secret-pass')
        ->and($rendered->body)->not->toContain('{{recipient_name}}')
        ->and($rendered->actionText)->toBe('Download App Now')
        ->and($rendered->actionUrl)->toBe('https://example.test/app');
});

test('edited copy is used when a learner welcome email is sent', function () {
    EmailTemplate::factory()->create([
        'key' => EmailTemplateKey::LearnerWelcome->value,
        'subject' => 'Custom welcome for {{recipient_name}}',
        'greeting' => 'Hi {{recipient_name}}',
        'body' => 'Your password is {{temporary_password}}',
        'salutation' => 'Bye',
        'action_text' => 'Open',
    ]);

    $learner = User::factory()->create([
        'name' => 'Sam Learner',
        'email' => 'sam.learner@example.com',
    ]);
    $instructor = Instructor::factory()->create();

    Notification::fake();

    $learner->notify(new WelcomeStudentNotification('secret-pass', $instructor));

    Notification::assertSentTo(
        $learner,
        WelcomeStudentNotification::class,
        function (WelcomeStudentNotification $notification) use ($learner): bool {
            $mail = $notification->toMail($learner);

            return $mail->subject === 'Custom welcome for Sam Learner';
        },
    );
});

test('edited instructor welcome subject is used without changing the setup link', function () {
    EmailTemplate::factory()->create([
        'key' => EmailTemplateKey::InstructorWelcome->value,
        'subject' => 'Please set up your account',
        'greeting' => 'Hello {{recipient_name}}',
        'body' => 'Use the button to continue.',
        'salutation' => '',
        'action_text' => 'Set up your account',
    ]);

    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $setupUrl = 'https://example.test/reset-password/token?email=jane%40example.com';

    $mail = new InstructorWelcomeMail($user, $setupUrl, 60);

    expect($mail->envelope()->subject)->toBe('Please set up your account')
        ->and($mail->setupUrl)->toBe($setupUrl);

    $html = $mail->render();

    expect($html)->toContain($setupUrl)
        ->and($html)->toContain('Set up your account');
});

test('html mailables include the edited sign-off without changing the login url', function () {
    EmailTemplate::factory()->create([
        'key' => EmailTemplateKey::LearnerPasswordReset->value,
        'subject' => 'Your password was reset',
        'greeting' => 'Hello {{recipient_name}}',
        'body' => 'Your new password is {{new_password}}.',
        'salutation' => 'Kind regards, Drive',
        'action_text' => 'Sign in now',
    ]);

    $user = User::factory()->create(['name' => 'Sam Learner', 'email' => 'sam@example.com']);
    $mail = new PupilPasswordResetMail($user, 'temp-pass');
    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe('Your password was reset')
        ->and($html)->toContain('Kind regards, Drive')
        ->and($html)->toContain('Sign in now')
        ->and($html)->toContain('temp-pass')
        ->and($html)->toContain(url('/login'));
});
