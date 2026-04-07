<?php

use App\Enums\PushNotificationStatus;
use App\Enums\UserRole;
use App\Models\PushNotification;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| POST /api/v1/push-token
|--------------------------------------------------------------------------
*/

test('a user can store their expo push token', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/push-token', [
        'expo_push_token' => 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Push token stored successfully.');

    expect($user->fresh()->expo_push_token)->toBe('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]');
});

test('store push token fails with invalid token format', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/push-token', [
        'expo_push_token' => 'invalid-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['expo_push_token']);
});

test('store push token fails when token is missing', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/push-token', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['expo_push_token']);
});

test('store push token requires authentication', function () {
    $response = $this->postJson('/api/v1/push-token', [
        'expo_push_token' => 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]',
    ]);

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| PushNotification Model
|--------------------------------------------------------------------------
*/

test('push notification belongs to a user', function () {
    $user = User::factory()->create();
    $notification = PushNotification::factory()->create(['user_id' => $user->id]);

    expect($notification->user->id)->toBe($user->id);
});

test('user has many push notifications', function () {
    $user = User::factory()->create();
    PushNotification::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->pushNotifications)->toHaveCount(3);
});
