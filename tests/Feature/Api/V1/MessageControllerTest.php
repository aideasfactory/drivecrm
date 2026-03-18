<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| GET /api/v1/messages/conversations
|--------------------------------------------------------------------------
*/

test('an instructor can list their conversations', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    Message::factory()->create([
        'from' => $instructorUser->id,
        'to' => $studentUser->id,
        'message' => 'Hello student!',
    ]);

    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/messages/conversations');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'user' => ['id', 'name'],
                    'latest_message' => ['id', 'message', 'is_own', 'created_at'],
                ],
            ],
        ])
        ->assertJsonPath('data.0.user.id', $studentUser->id)
        ->assertJsonPath('data.0.latest_message.message', 'Hello student!')
        ->assertJsonPath('data.0.latest_message.is_own', true);
});

test('a student can list their conversations', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    Message::factory()->create([
        'from' => $instructorUser->id,
        'to' => $studentUser->id,
        'message' => 'How is your practice going?',
    ]);

    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/messages/conversations');

    $response->assertOk()
        ->assertJsonPath('data.0.user.id', $instructorUser->id)
        ->assertJsonPath('data.0.latest_message.is_own', false);
});

test('listing conversations requires authentication', function () {
    $response = $this->getJson('/api/v1/messages/conversations');

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/messages/conversations/{user}
|--------------------------------------------------------------------------
*/

test('an instructor can view conversation with their student', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    Message::factory()->create([
        'from' => $instructorUser->id,
        'to' => $studentUser->id,
        'message' => 'First message',
    ]);
    Message::factory()->create([
        'from' => $studentUser->id,
        'to' => $instructorUser->id,
        'message' => 'Reply message',
    ]);

    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/messages/conversations/{$studentUser->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'sender_id', 'sender_name', 'recipient_id', 'message', 'is_own', 'created_at'],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('a student can view conversation with their instructor', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    Message::factory()->create([
        'from' => $instructorUser->id,
        'to' => $studentUser->id,
        'message' => 'Lesson reminder',
    ]);

    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/messages/conversations/{$instructorUser->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.message', 'Lesson reminder');
});

test('an instructor cannot view conversation with a student not assigned to them', function () {
    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $otherInstructor = Instructor::factory()->create();
    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $otherInstructor->id,
    ]);

    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/messages/conversations/{$studentUser->id}");

    $response->assertForbidden();
});

test('a student cannot view conversation with an instructor not assigned to them', function () {
    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    $otherInstructor = Instructor::factory()->create();
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $otherInstructor->id,
    ]);

    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/messages/conversations/{$instructorUser->id}");

    $response->assertForbidden();
});

test('viewing a conversation requires authentication', function () {
    $user = User::factory()->create();

    $response = $this->getJson("/api/v1/messages/conversations/{$user->id}");

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/messages
|--------------------------------------------------------------------------
*/

test('an instructor can send a message to their student', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', [
        'recipient_id' => $studentUser->id,
        'message' => 'Great lesson today!',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'sender_id', 'sender_name', 'recipient_id', 'message', 'is_own', 'created_at'],
        ])
        ->assertJsonPath('data.message', 'Great lesson today!')
        ->assertJsonPath('data.sender_id', $instructorUser->id)
        ->assertJsonPath('data.recipient_id', $studentUser->id)
        ->assertJsonPath('data.is_own', true);

    $this->assertDatabaseHas('messages', [
        'from' => $instructorUser->id,
        'to' => $studentUser->id,
        'message' => 'Great lesson today!',
    ]);
});

test('a student can send a message to their instructor', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', [
        'recipient_id' => $instructorUser->id,
        'message' => 'Thank you for the lesson!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.message', 'Thank you for the lesson!')
        ->assertJsonPath('data.sender_id', $studentUser->id)
        ->assertJsonPath('data.is_own', true);
});

test('an instructor cannot send a message to a student not assigned to them', function () {
    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $otherInstructor = Instructor::factory()->create();
    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $otherInstructor->id,
    ]);

    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', [
        'recipient_id' => $studentUser->id,
        'message' => 'This should fail.',
    ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('messages', [
        'from' => $instructorUser->id,
        'to' => $studentUser->id,
    ]);
});

test('a student cannot send a message to an instructor not assigned to them', function () {
    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    $otherInstructor = Instructor::factory()->create();
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $otherInstructor->id,
    ]);

    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', [
        'recipient_id' => $instructorUser->id,
        'message' => 'This should also fail.',
    ]);

    $response->assertForbidden();
});

test('sending a message requires authentication', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/messages', [
        'recipient_id' => $user->id,
        'message' => 'Should fail.',
    ]);

    $response->assertUnauthorized();
});

test('sending a message validates required fields', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['recipient_id', 'message']);
});

test('sending a message validates recipient exists', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', [
        'recipient_id' => 99999,
        'message' => 'Hello!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['recipient_id']);
});

test('sending a message validates message max length', function () {
    Notification::fake();

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create([
        'user_id' => $studentUser->id,
        'instructor_id' => $instructor->id,
    ]);

    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/messages', [
        'recipient_id' => $studentUser->id,
        'message' => str_repeat('a', 5001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});
