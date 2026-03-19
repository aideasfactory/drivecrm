<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\StudentPickupPoint;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/students/{student}/pickup-points
|--------------------------------------------------------------------------
*/

test('a student can view their own pickup points', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $pickupPoints = StudentPickupPoint::factory()->count(3)->create(['student_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/pickup-points");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'label', 'address', 'postcode', 'latitude', 'longitude', 'is_default', 'created_at', 'updated_at'],
            ],
        ]);
});

test('an instructor can view pickup points for a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    StudentPickupPoint::factory()->count(2)->create(['student_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/pickup-points");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('an instructor cannot view pickup points for a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);
    StudentPickupPoint::factory()->create(['student_id' => $student->id]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/pickup-points");

    $response->assertForbidden();
});

test('a student cannot view another student pickup points', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherStudent = Student::factory()->create(['status' => 'active']);
    StudentPickupPoint::factory()->create(['student_id' => $otherStudent->id]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$otherStudent->id}/pickup-points");

    $response->assertForbidden();
});

test('viewing pickup points requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->getJson("/api/v1/students/{$student->id}/pickup-points");

    $response->assertUnauthorized();
});

test('viewing pickup points for a non-existent student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/students/99999/pickup-points');

    $response->assertNotFound();
});

test('pickup points are returned with default first ordering', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);

    StudentPickupPoint::factory()->create([
        'student_id' => $student->id,
        'label' => 'School',
        'is_default' => false,
    ]);
    StudentPickupPoint::factory()->create([
        'student_id' => $student->id,
        'label' => 'Home',
        'is_default' => true,
    ]);

    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/pickup-points");

    $response->assertOk();
    $data = $response->json('data');
    expect($data[0]['is_default'])->toBeTrue()
        ->and($data[0]['label'])->toBe('Home');
});

test('student with no pickup points returns empty data array', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/pickup-points");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});
