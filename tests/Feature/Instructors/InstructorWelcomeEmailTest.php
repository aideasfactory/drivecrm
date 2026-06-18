<?php

declare(strict_types=1);

use App\Actions\Instructor\SendInstructorWelcomeEmailAction;
use App\Enums\UserRole;
use App\Mail\InstructorWelcomeMail;
use App\Models\Instructor;
use App\Models\User;
use App\Services\InstructorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Stub postcodes.io so InstructorService::createInstructor can resolve coords.
    Http::fake([
        'api.postcodes.io/*' => Http::response([
            'status' => 200,
            'result' => [
                'latitude' => 54.0,
                'longitude' => -1.0,
            ],
        ]),
    ]);
});

test('creating an instructor sends a welcome email with a password-setup link', function () {
    Mail::fake();

    /** @var InstructorService $service */
    $service = app(InstructorService::class);

    $result = $service->createInstructor([
        'name' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
        'postcode' => 'TS7 8BA',
        'transmission_type' => 'manual',
    ]);

    expect($result['success'])->toBeTrue();

    $user = User::where('email', 'jane.doe@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::INSTRUCTOR)
        ->and($user->password_change_required)->toBeTrue()
        ->and($user->welcome_email_pending)->toBeFalse(); // flipped back to false on send success

    Mail::assertQueued(InstructorWelcomeMail::class, function (InstructorWelcomeMail $mail) use ($user) {
        $mail->build();

        return $mail->hasTo($user->email)
            && str_contains($mail->setupUrl, '/reset-password/')
            && str_contains($mail->setupUrl, urlencode($user->email));
    });
});

test('the setup link in the welcome email is a valid password broker token', function () {
    Mail::fake();

    /** @var InstructorService $service */
    $service = app(InstructorService::class);

    $service->createInstructor([
        'name' => 'Token Test',
        'email' => 'token.test@example.com',
        'postcode' => 'TS7 8BA',
        'transmission_type' => 'manual',
    ]);

    $user = User::where('email', 'token.test@example.com')->firstOrFail();

    $captured = null;
    Mail::assertQueued(InstructorWelcomeMail::class, function (InstructorWelcomeMail $mail) use (&$captured) {
        $captured = $mail;

        return true;
    });

    expect($captured)->not->toBeNull();

    // Pull the token out of the route('password.reset', ...) URL.
    $path = parse_url($captured->setupUrl, PHP_URL_PATH) ?: '';
    $segments = explode('/', trim($path, '/'));
    $token = end($segments);

    expect(Password::broker()->tokenExists($user, $token))->toBeTrue();
});

test('the default password is not the literal string "password"', function () {
    Mail::fake();

    /** @var InstructorService $service */
    $service = app(InstructorService::class);

    $service->createInstructor([
        'name' => 'Strong Default',
        'email' => 'strong.default@example.com',
        'postcode' => 'TS7 8BA',
        'transmission_type' => 'manual',
    ]);

    $user = User::where('email', 'strong.default@example.com')->firstOrFail();

    expect(Hash::check('password', $user->password))->toBeFalse();
});

test('owners can resend the welcome email via the resend-invite endpoint', function () {
    Mail::fake();

    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($owner);

    $instructorUser = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'email' => 'resend.target@example.com',
        'welcome_email_pending' => true,
    ]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $response = $this->postJson(route('instructors.resend-invite', $instructor));

    $response->assertOk();
    $response->assertJson(['welcome_email_pending' => false]);

    Mail::assertQueued(InstructorWelcomeMail::class, fn (InstructorWelcomeMail $mail) => $mail->hasTo('resend.target@example.com'));

    $instructorUser->refresh();
    expect($instructorUser->welcome_email_pending)->toBeFalse();
});

test('non-owners cannot resend the welcome email', function () {
    Mail::fake();

    $instructorActor = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $this->actingAs($instructorActor);

    $targetUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $target = Instructor::factory()->create(['user_id' => $targetUser->id]);

    $response = $this->postJson(route('instructors.resend-invite', $target));

    $response->assertForbidden();
    Mail::assertNothingQueued();
});

test('send action leaves welcome_email_pending = true when sending fails', function () {
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'welcome_email_pending' => false,
    ]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);

    // Force the mailer to throw on send.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $action = app(SendInstructorWelcomeEmailAction::class);
    $sent = $action($instructor);

    expect($sent)->toBeFalse();

    $user->refresh();
    expect($user->welcome_email_pending)->toBeTrue();
});

test('bulk-imported instructors each receive a welcome email', function () {
    Mail::fake();

    /** @var InstructorService $service */
    $service = app(InstructorService::class);

    $result = $service->bulkImportInstructors([
        [
            'name' => 'Bulk One',
            'email' => 'bulk.one@example.com',
            'transmission_type' => 'manual',
        ],
        [
            'name' => 'Bulk Two',
            'email' => 'bulk.two@example.com',
            'transmission_type' => 'automatic',
        ],
    ]);

    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(0);

    Mail::assertQueued(InstructorWelcomeMail::class, 2);
    Mail::assertQueued(InstructorWelcomeMail::class, fn (InstructorWelcomeMail $mail) => $mail->hasTo('bulk.one@example.com'));
    Mail::assertQueued(InstructorWelcomeMail::class, fn (InstructorWelcomeMail $mail) => $mail->hasTo('bulk.two@example.com'));
});

test('instructor show page exposes welcome_email_pending flag', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($owner);

    $targetUser = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'welcome_email_pending' => true,
    ]);
    $target = Instructor::factory()->create(['user_id' => $targetUser->id]);

    $response = $this->get(route('instructors.show', $target));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('instructor.welcome_email_pending', true)
    );
});
