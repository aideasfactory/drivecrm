<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| POST /api/v1/instructor/profile/picture
|--------------------------------------------------------------------------
*/

test('an instructor can upload a profile picture', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(1024);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/profile/picture', [
        'profile_picture' => $file,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'profile_picture_url',
            ],
        ]);

    $instructor->refresh();
    expect($instructor->profile_picture_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($instructor->profile_picture_path);
});

test('an instructor can replace their existing profile picture', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create([
        'user_id' => $user->id,
        'profile_picture_path' => 'instructors/1/profile/old-avatar.jpg',
    ]);
    Storage::disk('s3')->put('instructors/1/profile/old-avatar.jpg', 'old-content');
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('new-avatar.png', 300, 300)->size(2048);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/profile/picture', [
        'profile_picture' => $file,
    ]);

    $response->assertOk();

    $instructor->refresh();
    expect($instructor->profile_picture_path)->not->toBe('instructors/1/profile/old-avatar.jpg');
    Storage::disk('s3')->assertMissing('instructors/1/profile/old-avatar.jpg');
    Storage::disk('s3')->assertExists($instructor->profile_picture_path);
});

test('profile picture upload validates file type', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/profile/picture', [
        'profile_picture' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('profile_picture');
});

test('profile picture upload validates file size', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('huge-avatar.jpg')->size(6000);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/profile/picture', [
        'profile_picture' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('profile_picture');
});

test('profile picture upload requires authentication', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->postJson('/api/v1/instructor/profile/picture', [
        'profile_picture' => $file,
    ]);

    $response->assertUnauthorized();
});

test('a student cannot upload an instructor profile picture', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/profile/picture', [
        'profile_picture' => $file,
    ]);

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/instructor/profile/picture
|--------------------------------------------------------------------------
*/

test('an instructor can delete their profile picture', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create([
        'user_id' => $user->id,
        'profile_picture_path' => 'instructors/1/profile/avatar.jpg',
    ]);
    Storage::disk('s3')->put('instructors/1/profile/avatar.jpg', 'content');
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/v1/instructor/profile/picture');

    $response->assertOk()
        ->assertJsonPath('data.profile_picture_url', null);

    $instructor->refresh();
    expect($instructor->profile_picture_path)->toBeNull();
    Storage::disk('s3')->assertMissing('instructors/1/profile/avatar.jpg');
});

test('deleting a profile picture when none exists is a no-op', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create([
        'user_id' => $user->id,
        'profile_picture_path' => null,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/v1/instructor/profile/picture');

    $response->assertOk();
});

test('profile picture delete requires authentication', function () {
    $response = $this->deleteJson('/api/v1/instructor/profile/picture');

    $response->assertUnauthorized();
});
