<?php

use App\Enums\UserRole;
use App\Models\Resource;
use App\Models\ResourceFolder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

test('owner can upload a file resource', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->post('/resources/files', [
        'resource_type' => 'file',
        'file' => UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4'),
        'title' => 'Test Video',
        'description' => 'A test video file',
        'resource_folder_id' => $folder->id,
        'tags' => ['driving', 'roundabout'],
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['message' => 'Resource created successfully.']);

    $resource = Resource::first();
    expect($resource)->not->toBeNull()
        ->and($resource->resource_type)->toBe('file')
        ->and($resource->title)->toBe('Test Video')
        ->and($resource->description)->toBe('A test video file')
        ->and($resource->video_url)->toBeNull()
        ->and($resource->file_path)->not->toBeNull()
        ->and($resource->file_name)->toBe('video.mp4')
        ->and($resource->mime_type)->toBe('video/mp4')
        ->and($resource->tags)->toBe(['driving', 'roundabout']);
});

test('owner can store a video link resource', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->post('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'YouTube Tutorial',
        'description' => 'A tutorial video',
        'resource_folder_id' => $folder->id,
        'tags' => ['tutorial'],
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['message' => 'Resource created successfully.']);

    $resource = Resource::first();
    expect($resource)->not->toBeNull()
        ->and($resource->resource_type)->toBe('video_link')
        ->and($resource->video_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($resource->title)->toBe('YouTube Tutorial')
        ->and($resource->file_path)->toBeNull()
        ->and($resource->file_name)->toBeNull()
        ->and($resource->file_size)->toBeNull()
        ->and($resource->mime_type)->toBeNull();
});

test('video link requires a valid youtube or vimeo url', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.example.com/not-a-video',
        'title' => 'Invalid Video',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['video_url']);
});

test('file upload requires a file when type is file', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'file',
        'title' => 'Missing File',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

test('video link does not require a file', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://vimeo.com/123456789',
        'title' => 'Vimeo Video',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(201);

    $resource = Resource::first();
    expect($resource->resource_type)->toBe('video_link')
        ->and($resource->video_url)->toBe('https://vimeo.com/123456789');
});

test('non-owner cannot store resources', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Unauthorized',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(403);
});
