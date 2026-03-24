<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Note;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/students/{student}/notes
|--------------------------------------------------------------------------
*/

test('a student can view their own notes', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    Note::factory()->count(3)->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/notes");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'note', 'created_at', 'updated_at'],
            ],
        ]);
});

test('an instructor can view notes for a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    Note::factory()->count(2)->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/notes");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('an instructor cannot view notes for a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/notes");

    $response->assertForbidden();
});

test('a student cannot view another student notes', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherStudent = Student::factory()->create(['status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$otherStudent->id}/notes");

    $response->assertForbidden();
});

test('viewing student notes requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->getJson("/api/v1/students/{$student->id}/notes");

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/students/{student}/notes
|--------------------------------------------------------------------------
*/

test('a student can create a note on their own record', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/notes", [
        'note' => 'This is a test note.',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'note', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('data.note', 'This is a test note.');

    $this->assertDatabaseHas('notes', [
        'noteable_type' => Student::class,
        'noteable_id' => $student->id,
        'note' => 'This is a test note.',
    ]);
});

test('an instructor can create a note on a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/notes", [
        'note' => 'Instructor note about student.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.note', 'Instructor note about student.');
});

test('an instructor cannot create a note on a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/notes", [
        'note' => 'Should not work.',
    ]);

    $response->assertForbidden();
});

test('a student cannot create a note on another student', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherStudent = Student::factory()->create(['status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$otherStudent->id}/notes", [
        'note' => 'Should not work.',
    ]);

    $response->assertForbidden();
});

test('creating a note requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $response = $this->postJson("/api/v1/students/{$student->id}/notes", [
        'note' => 'Should not work.',
    ]);

    $response->assertUnauthorized();
});

test('creating a note requires the note field', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/notes", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});

test('creating a note rejects notes exceeding max length', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/notes", [
        'note' => str_repeat('a', 5001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/students/{student}/notes/{note}
|--------------------------------------------------------------------------
*/

test('a student can update their own note', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id, 'note' => 'Original']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", [
        'note' => 'Updated note content.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.note', 'Updated note content.')
        ->assertJsonStructure([
            'data' => ['id', 'note', 'created_at', 'updated_at'],
        ]);

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'note' => 'Updated note content.',
    ]);
});

test('an instructor can update a note on a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", [
        'note' => 'Instructor updated this note.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.note', 'Instructor updated this note.');
});

test('an instructor cannot update a note on a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", [
        'note' => 'Should not work.',
    ]);

    $response->assertForbidden();
});

test('updating a note that does not belong to the student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $otherStudent = Student::factory()->create(['status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $otherStudent->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", [
        'note' => 'Should not work.',
    ]);

    $response->assertNotFound();
});

test('updating a note requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);

    $response = $this->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", [
        'note' => 'Should not work.',
    ]);

    $response->assertUnauthorized();
});

test('updating a note requires the note field', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});

test('updating a note rejects notes exceeding max length', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson("/api/v1/students/{$student->id}/notes/{$note->id}", [
        'note' => str_repeat('a', 5001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/students/{student}/notes/{note}
|--------------------------------------------------------------------------
*/

test('a student can delete their own note', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}/notes/{$note->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('notes', ['id' => $note->id]);
});

test('an instructor can delete a note on a student assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}/notes/{$note->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('notes', ['id' => $note->id]);
});

test('an instructor cannot delete a note on a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}/notes/{$note->id}");

    $response->assertForbidden();
});

test('deleting a note that does not belong to the student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $otherStudent = Student::factory()->create(['status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $otherStudent->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}/notes/{$note->id}");

    $response->assertNotFound();
});

test('deleting a note requires authentication', function () {
    $student = Student::factory()->create(['status' => 'active']);
    $note = Note::factory()->create(['noteable_type' => Student::class, 'noteable_id' => $student->id]);

    $response = $this->deleteJson("/api/v1/students/{$student->id}/notes/{$note->id}");

    $response->assertUnauthorized();
});
