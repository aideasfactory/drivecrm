<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/students/{student}
|--------------------------------------------------------------------------
*/

test('a student can view their own record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'first_name', 'surname', 'email', 'phone', 'status', 'has_app', 'updated_at'],
        ])
        ->assertJsonPath('data.id', $student->id);
});

test('an instructor can view a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $student->id);
});

test('an instructor cannot view a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}");

    $response->assertForbidden();
});

test('a student cannot view another student record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherStudent = Student::factory()->create(['status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$otherStudent->id}");

    $response->assertForbidden();
});

test('viewing a student record requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->getJson("/api/v1/students/{$student->id}");

    $response->assertUnauthorized();
});

test('viewing a non-existent student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/students/99999');

    $response->assertNotFound();
});
