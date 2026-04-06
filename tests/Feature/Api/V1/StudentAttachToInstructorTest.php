<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| POST /api/v1/students/attach
|--------------------------------------------------------------------------
*/

test('a student can attach to an instructor via valid PIN', function () {
    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $studentUser->id, 'instructor_id' => null]);
    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $instructor = Instructor::factory()->create(['pin' => 'ABC123']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students/attach', [
        'pin' => 'ABC123',
    ]);

    $response->assertOk()
        ->assertExactJson(true);

    expect($student->fresh()->instructor_id)->toBe($instructor->id);
});

test('attach fails when PIN does not match any instructor', function () {
    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $studentUser->id, 'instructor_id' => null]);
    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students/attach', [
        'pin' => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'The PIN you entered does not match any instructor.');
});

test('attach fails when student already has an instructor', function () {
    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    $existingInstructor = Instructor::factory()->create();
    Student::factory()->create(['user_id' => $studentUser->id, 'instructor_id' => $existingInstructor->id]);
    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $newInstructor = Instructor::factory()->create(['pin' => 'XYZ789']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students/attach', [
        'pin' => 'XYZ789',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'You are already attached to an instructor.');
});

test('attach fails with validation error when pin is missing', function () {
    $studentUser = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $studentUser->id, 'instructor_id' => null]);
    $token = $studentUser->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students/attach', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);
});

test('attach requires authentication', function () {
    $response = $this->postJson('/api/v1/students/attach', [
        'pin' => 'ABC123',
    ]);

    $response->assertUnauthorized();
});
