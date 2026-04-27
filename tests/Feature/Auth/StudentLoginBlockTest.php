<?php

use App\Enums\UserRole;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Login — Student Block
|--------------------------------------------------------------------------
*/

test('student users cannot log in via the web', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('instructor users can still log in via the web', function () {
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('owner users can still log in via the web', function () {
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

/*
|--------------------------------------------------------------------------
| API Login — Student Block
|--------------------------------------------------------------------------
*/

test('student users cannot log in via the API', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
        'role' => 'student',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('instructor users can still log in via the API', function () {
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
        'role' => 'instructor',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

/*
|--------------------------------------------------------------------------
| API Registration — Student Block
|--------------------------------------------------------------------------
*/

test('student registration via the API is temporarily blocked', function () {
    $response = $this->postJson('/api/v1/auth/register/student', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '07700900000',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertForbidden()
        ->assertJson(['message' => 'Student registration is temporarily unavailable. Please try again later.']);
});

/*
|--------------------------------------------------------------------------
| RestrictStudent Middleware — Existing Sessions
|--------------------------------------------------------------------------
*/

test('authenticated student users are blocked from accessing protected web routes', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('authenticated student users are blocked from accessing protected API routes', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/auth/user');

    $response->assertForbidden()
        ->assertJson(['message' => 'Student access is temporarily unavailable.']);
});

test('authenticated instructor users are not blocked by RestrictStudent middleware', function () {
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/auth/user');

    $response->assertOk();
});
