<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\StudentChecklistItem;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/students/{student}/checklist-items
|--------------------------------------------------------------------------
*/

test('a student can view their own checklist items', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    StudentChecklistItem::factory()->count(3)->create(['student_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/checklist-items");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'key', 'label', 'category', 'is_checked', 'date', 'notes', 'sort_order'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('checklist items are lazy-seeded when none exist', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/checklist-items");

    $response->assertOk()
        ->assertJsonCount(count(StudentChecklistItem::defaultItems()), 'data');
});

test('an instructor can view checklist items for their student', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    StudentChecklistItem::factory()->count(2)->create(['student_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/checklist-items");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('an instructor cannot view checklist items for a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/checklist-items");

    $response->assertForbidden();
});

test('a student cannot view another students checklist items', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $otherStudent = Student::factory()->create(['status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$otherStudent->id}/checklist-items");

    $response->assertForbidden();
});

test('viewing checklist items requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->getJson("/api/v1/students/{$student->id}/checklist-items");

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/students/{student}/checklist-items/{checklistItem}
|--------------------------------------------------------------------------
*/

test('a student can update their own checklist item', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $item = StudentChecklistItem::factory()->create(['student_id' => $student->id, 'is_checked' => false]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'is_checked' => true,
        'date' => '2026-03-18',
        'notes' => 'Completed successfully',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.is_checked', true)
        ->assertJsonPath('data.date', '2026-03-18')
        ->assertJsonPath('data.notes', 'Completed successfully');
});

test('an instructor can update a checklist item for their student', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $item = StudentChecklistItem::factory()->create(['student_id' => $student->id, 'is_checked' => false]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'is_checked' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.is_checked', true);
});

test('an instructor cannot update checklist items for a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);
    $item = StudentChecklistItem::factory()->create(['student_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'is_checked' => true,
    ]);

    $response->assertForbidden();
});

test('updating a checklist item that does not belong to the student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $otherStudent = Student::factory()->create();
    $item = StudentChecklistItem::factory()->create(['student_id' => $otherStudent->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'is_checked' => true,
    ]);

    $response->assertNotFound();
});

test('updating a checklist item validates input', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $item = StudentChecklistItem::factory()->create(['student_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'is_checked' => 'not-a-boolean',
        'date' => 'not-a-date',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['is_checked', 'date']);
});

test('updating a checklist item requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);
    $item = StudentChecklistItem::factory()->create(['student_id' => $student->id]);

    $response = $this->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'is_checked' => true,
    ]);

    $response->assertUnauthorized();
});

test('partial update works with only some fields', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $item = StudentChecklistItem::factory()->create([
        'student_id' => $student->id,
        'is_checked' => false,
        'notes' => 'Original notes',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/checklist-items/{$item->id}", [
        'notes' => 'Updated notes',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.notes', 'Updated notes')
        ->assertJsonPath('data.is_checked', false);
});
