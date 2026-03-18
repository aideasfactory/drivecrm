<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| PUT /api/v1/instructor/profile
|--------------------------------------------------------------------------
*/

test('an instructor can update their own profile', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create([
        'user_id' => $user->id,
        'bio' => null,
        'transmission_type' => 'manual',
        'address' => '1 Old Street',
        'postcode' => 'TS1 1AA',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/profile', [
        'bio' => 'Updated bio text',
        'transmission_type' => 'both',
        'address' => '10 New Street',
        'postcode' => 'TS7 0AB',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.bio', 'Updated bio text')
        ->assertJsonPath('data.transmission_type', 'both')
        ->assertJsonPath('data.address', '10 New Street')
        ->assertJsonPath('data.postcode', 'TS7 0AB');

    $this->assertDatabaseHas('instructors', [
        'id' => $instructor->id,
        'bio' => 'Updated bio text',
        'transmission_type' => 'both',
        'address' => '10 New Street',
        'postcode' => 'TS7 0AB',
    ]);
});

test('an instructor can partially update their profile', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create([
        'user_id' => $user->id,
        'bio' => 'Original bio',
        'transmission_type' => 'manual',
        'address' => '1 Old Street',
        'postcode' => 'TS1 1AA',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/profile', [
        'bio' => 'Only bio changed',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.bio', 'Only bio changed')
        ->assertJsonPath('data.transmission_type', 'manual')
        ->assertJsonPath('data.address', '1 Old Street')
        ->assertJsonPath('data.postcode', 'TS1 1AA');
});

test('a student cannot access the instructor profile update endpoint', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/profile', [
        'bio' => 'Should not work',
    ]);

    $response->assertForbidden();
});

test('update instructor profile requires authentication', function () {
    $response = $this->putJson('/api/v1/instructor/profile', [
        'bio' => 'No token provided',
    ]);

    $response->assertUnauthorized();
});

test('update instructor profile validates transmission_type', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/profile', [
        'transmission_type' => 'invalid_value',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('transmission_type');
});

test('update instructor profile validates bio max length', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/profile', [
        'bio' => str_repeat('a', 1001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('bio');
});

test('update instructor profile returns the expected resource structure', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/profile', [
        'bio' => 'Test bio',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'bio',
                'transmission_type',
                'status',
                'address',
                'postcode',
                'onboarding_complete',
                'charges_enabled',
                'payouts_enabled',
            ],
        ]);
});
