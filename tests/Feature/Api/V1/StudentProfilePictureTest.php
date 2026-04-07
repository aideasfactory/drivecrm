<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| POST /api/v1/students/{student}/profile-picture
|--------------------------------------------------------------------------
*/

test('a student can upload their own profile picture', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(1024);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'profile_picture_url',
            ],
        ]);

    $student->refresh();
    expect($student->profile_picture_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($student->profile_picture_path);
});

test('an instructor can upload their students profile picture', function () {
    Storage::fake('s3');

    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('avatar.png', 300, 300)->size(2048);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertOk();

    $student->refresh();
    expect($student->profile_picture_path)->not->toBeNull();
});

test('a student can replace their existing profile picture', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'profile_picture_path' => 'students/1/profile/old-avatar.jpg',
    ]);
    Storage::disk('s3')->put('students/1/profile/old-avatar.jpg', 'old-content');
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('new-avatar.png', 300, 300)->size(2048);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertOk();

    $student->refresh();
    expect($student->profile_picture_path)->not->toBe('students/1/profile/old-avatar.jpg');
    Storage::disk('s3')->assertMissing('students/1/profile/old-avatar.jpg');
    Storage::disk('s3')->assertExists($student->profile_picture_path);
});

test('student profile picture upload validates file type', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('profile_picture');
});

test('student profile picture upload validates file size', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('huge-avatar.jpg')->size(6000);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('profile_picture');
});

test('student profile picture upload requires authentication', function () {
    $student = Student::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertUnauthorized();
});

test('an unrelated instructor cannot upload a students profile picture', function () {
    $instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);
    $student = Student::factory()->create(); // no instructor_id or different instructor
    $token = $instructorUser->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/profile-picture", [
        'profile_picture' => $file,
    ]);

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/students/{student}/profile-picture
|--------------------------------------------------------------------------
*/

test('a student can delete their profile picture', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'profile_picture_path' => 'students/1/profile/avatar.jpg',
    ]);
    Storage::disk('s3')->put('students/1/profile/avatar.jpg', 'content');
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}/profile-picture");

    $response->assertOk()
        ->assertJsonPath('data.profile_picture_url', null);

    $student->refresh();
    expect($student->profile_picture_path)->toBeNull();
    Storage::disk('s3')->assertMissing('students/1/profile/avatar.jpg');
});

test('deleting a student profile picture when none exists is a no-op', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'profile_picture_path' => null,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/students/{$student->id}/profile-picture");

    $response->assertOk();
});

test('student profile picture delete requires authentication', function () {
    $student = Student::factory()->create();

    $response = $this->deleteJson("/api/v1/students/{$student->id}/profile-picture");

    $response->assertUnauthorized();
});
