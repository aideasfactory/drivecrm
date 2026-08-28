<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\InstructorWelcomeMail;
use App\Models\Instructor;
use App\Models\User;
use App\Services\InstructorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response([
            'status' => 200,
            'result' => [
                'latitude' => 53.4808,
                'longitude' => -2.2426,
            ],
        ]),
    ]);
});

test('a password passed to createInstructor is not persisted', function () {
    Mail::fake();

    $chosenPassword = 'StaffSetPass1!';

    $instructor = app(InstructorService::class)->createInstructor([
        'name' => 'Pat Instructor',
        'email' => 'pat.instructor@example.com',
        'password' => $chosenPassword,
        'postcode' => 'M1 1AA',
        'transmission_type' => 'manual',
    ]);

    expect($instructor)->toBeInstanceOf(Instructor::class);

    $user = $instructor->user()->firstOrFail();

    expect(Hash::check($chosenPassword, $user->password))->toBeFalse()
        ->and(Hash::check('password123', $user->password))->toBeFalse()
        ->and($user->password_change_required)->toBeTrue()
        ->and($user->role)->toBe(UserRole::INSTRUCTOR);

    Mail::assertQueued(InstructorWelcomeMail::class, fn (InstructorWelcomeMail $mail) => $mail->hasTo($user->email));
});

test('posting a password when creating an instructor does not let them log in with it', function () {
    Mail::fake();

    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($owner);

    $chosenPassword = 'StaffSetPass1!';

    $this->post(route('instructors.store'), [
        'name' => 'Pat Instructor',
        'email' => 'pat.store@example.com',
        'password' => $chosenPassword,
        'transmission_type' => 'manual',
        'postcode' => 'M1 1AA',
    ])->assertRedirect(route('instructors.index'))
        ->assertSessionHas('success');

    $user = User::where('email', 'pat.store@example.com')->firstOrFail();

    expect(Hash::check($chosenPassword, $user->password))->toBeFalse()
        ->and(Hash::check('password123', $user->password))->toBeFalse();

    Mail::assertQueued(InstructorWelcomeMail::class);

    $this->post(route('logout'));
    $this->assertGuest();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => $chosenPassword,
    ]);

    $this->assertGuest();
});

test('an instructor can set a password from the welcome email and log into the admin area', function () {
    Mail::fake();

    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($owner);

    $this->post(route('instructors.store'), [
        'name' => 'Pat Instructor',
        'email' => 'pat.setup@example.com',
        'transmission_type' => 'manual',
        'postcode' => 'M1 1AA',
    ])->assertRedirect(route('instructors.index'));

    $user = User::where('email', 'pat.setup@example.com')->firstOrFail();
    $instructor = $user->instructor()->firstOrFail();

    $captured = null;
    Mail::assertQueued(InstructorWelcomeMail::class, function (InstructorWelcomeMail $mail) use (&$captured) {
        $captured = $mail;

        return true;
    });

    expect($captured)->not->toBeNull();

    $path = parse_url($captured->setupUrl, PHP_URL_PATH) ?: '';
    $segments = explode('/', trim($path, '/'));
    $token = end($segments);

    expect(Password::broker()->tokenExists($user, $token))->toBeTrue();

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();

    expect($user->password_change_required)->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeTrue();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('instructors.show', $instructor));

    $this->get(route('instructors.show', $instructor))->assertOk();
});
