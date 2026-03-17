<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/instructor/students
|--------------------------------------------------------------------------
*/

test('an instructor can retrieve their grouped students', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    // Create students with different statuses
    Student::factory()->count(2)->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'passed']);
    Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'inactive']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/students');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'active',
                'passed',
                'inactive',
                'recent_activity',
            ],
        ])
        ->assertJsonCount(2, 'data.active')
        ->assertJsonCount(1, 'data.passed')
        ->assertJsonCount(1, 'data.inactive');
});

test('recent_activity returns a maximum of 5 students', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    Student::factory()->count(8)->create(['instructor_id' => $instructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/students');

    $response->assertOk()
        ->assertJsonCount(5, 'data.recent_activity');
});

test('students are scoped to the authenticated instructor only', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    // Create students for this instructor
    Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);

    // Create students for a different instructor
    $otherInstructor = Instructor::factory()->create();
    Student::factory()->count(3)->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/students');

    $response->assertOk()
        ->assertJsonCount(1, 'data.active');
});

test('student resource includes expected fields', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/students');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'active' => [
                    ['id', 'first_name', 'surname', 'email', 'phone', 'status', 'has_app', 'updated_at'],
                ],
            ],
        ]);
});

test('get grouped students requires authentication', function () {
    $response = $this->getJson('/api/v1/instructor/students');

    $response->assertUnauthorized();
});

test('empty groups return empty arrays', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/students');

    $response->assertOk()
        ->assertJsonCount(0, 'data.active')
        ->assertJsonCount(0, 'data.passed')
        ->assertJsonCount(0, 'data.inactive')
        ->assertJsonCount(0, 'data.recent_activity');
});
