<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/

test('an authenticated user can change their password', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
        'password_change_required' => true,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $user->refresh();
    expect(Hash::check('NewSecurePassword123!', $user->password))->toBeTrue();
    expect($user->password_change_required)->toBeFalse();
});

test('change password fails with incorrect current password', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
        'password_change_required' => true,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/auth/change-password', [
        'current_password' => 'wrong-password',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $user->refresh();
    expect($user->password_change_required)->toBeTrue();
});

test('change password validates required fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/auth/change-password', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password', 'password']);
});

test('change password requires password confirmation', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password',
        'password' => 'NewSecurePassword123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('change password requires authentication', function () {
    $response = $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Password Change Required Flag
|--------------------------------------------------------------------------
*/

test('login response includes password_change_required field', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
        'password_change_required' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.password_change_required', true);
});

test('login response shows false when password change is not required', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
        'password_change_required' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.password_change_required', false);
});

test('get user endpoint includes password_change_required field', function () {
    $user = User::factory()->create([
        'role' => UserRole::STUDENT,
        'password_change_required' => true,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.password_change_required', true);
});
