<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

test('a user can login via the API and receive a token', function () {
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
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role', 'email_verified_at', 'created_at'],
        ])
        ->assertJsonPath('user.role', 'instructor');
});

test('login returns the correct role for instructors', function () {
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.role', 'instructor');
});

test('login fails with invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Test Device',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

test('an authenticated user can logout via the API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Logged out successfully.']);

    expect($user->tokens()->count())->toBe(0);
});

test('logout requires authentication', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Get Authenticated User
|--------------------------------------------------------------------------
*/

test('an authenticated user can retrieve their profile', function () {
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role', 'email_verified_at', 'created_at'],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'instructor');
});

test('get user requires authentication', function () {
    $response = $this->getJson('/api/v1/auth/user');

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Student Registration
|--------------------------------------------------------------------------
*/

test('student registration is temporarily blocked', function () {
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

test('student registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/auth/register/student', [
        'name' => 'Jane Doe',
        'email' => 'existing@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('student registration validates required fields', function () {
    $response = $this->postJson('/api/v1/auth/register/student', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
});

/*
|--------------------------------------------------------------------------
| Instructor Registration
|--------------------------------------------------------------------------
*/

test('an instructor can register via the API', function () {
    $response = $this->postJson('/api/v1/auth/register/instructor', [
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '07700900001',
        'postcode' => 'TS7 0AB',
        'address' => '1 High Street',
        'transmission_type' => 'manual',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role', 'email_verified_at', 'created_at'],
        ])
        ->assertJsonPath('user.role', 'instructor')
        ->assertJsonPath('user.name', 'John Smith');

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'role' => UserRole::INSTRUCTOR->value,
    ]);

    $this->assertDatabaseHas('instructors', [
        'postcode' => 'TS7 0AB',
        'address' => '1 High Street',
        'status' => 'active',
        'onboarding_complete' => false,
    ]);
});

test('instructor registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register/instructor', [
        'name' => 'John Smith',
        'email' => 'taken@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('instructor registration validates required fields', function () {
    $response = $this->postJson('/api/v1/auth/register/instructor', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
});

test('instructor registration validates transmission type options', function () {
    $response = $this->postJson('/api/v1/auth/register/instructor', [
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'transmission_type' => 'invalid',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('transmission_type');
});
