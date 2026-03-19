<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| POST /api/v1/students
|--------------------------------------------------------------------------
*/

test('an instructor can create a student', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students', [
        'first_name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'phone' => '07700900000',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'first_name', 'surname', 'email', 'phone', 'status', 'has_app', 'updated_at'],
        ])
        ->assertJsonPath('data.first_name', 'Jane')
        ->assertJsonPath('data.surname', 'Doe')
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->assertDatabaseHas('students', [
        'instructor_id' => $instructor->id,
        'first_name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'status' => 'active',
    ]);
});

test('a student cannot create a student', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students', [
        'first_name' => 'Jane',
        'surname' => 'Doe',
    ]);

    $response->assertForbidden();
});

test('creating a student requires authentication', function () {
    $response = $this->postJson('/api/v1/students', [
        'first_name' => 'Jane',
        'surname' => 'Doe',
    ]);

    $response->assertUnauthorized();
});

test('creating a student validates required fields', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'surname']);
});

test('creating a student with contact details', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/students', [
        'first_name' => 'Tom',
        'surname' => 'Brown',
        'email' => 'tom@example.com',
        'phone' => '07700900001',
        'contact_first_name' => 'Mary',
        'contact_surname' => 'Brown',
        'contact_email' => 'mary@example.com',
        'contact_phone' => '07700900002',
        'owns_account' => false,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('students', [
        'instructor_id' => $instructor->id,
        'first_name' => 'Tom',
        'contact_first_name' => 'Mary',
        'owns_account' => false,
    ]);
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/students/{student}
|--------------------------------------------------------------------------
*/

test('a student can update their own record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'first_name' => 'Jane', 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}", [
        'first_name' => 'Janet',
        'phone' => '07700900099',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.first_name', 'Janet')
        ->assertJsonPath('data.phone', '07700900099');

    $this->assertDatabaseHas('students', [
        'id' => $student->id,
        'first_name' => 'Janet',
        'phone' => '07700900099',
    ]);
});

test('an instructor can update a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}", [
        'first_name' => 'Updated',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.first_name', 'Updated');
});

test('an instructor cannot update a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}", [
        'first_name' => 'Hacked',
    ]);

    $response->assertForbidden();
});

test('a student cannot update another student record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherStudent = Student::factory()->create(['status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$otherStudent->id}", [
        'first_name' => 'Hacked',
    ]);

    $response->assertForbidden();
});

test('updating a student requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->putJson("/api/v1/students/{$student->id}", [
        'first_name' => 'Updated',
    ]);

    $response->assertUnauthorized();
});

test('updating a non-existent student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/students/99999', [
        'first_name' => 'Updated',
    ]);

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/students/{student}
|--------------------------------------------------------------------------
*/

test('a student can delete their own record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('students', ['id' => $student->id]);
});

test('an instructor can delete a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('students', ['id' => $student->id]);
});

test('an instructor cannot delete a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('students', ['id' => $student->id]);
});

test('a student cannot delete another student record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherStudent = Student::factory()->create(['status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$otherStudent->id}");

    $response->assertForbidden();
});

test('deleting a student requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->deleteJson("/api/v1/students/{$student->id}");

    $response->assertUnauthorized();
});

test('deleting a non-existent student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/v1/students/99999');

    $response->assertNotFound();
});
