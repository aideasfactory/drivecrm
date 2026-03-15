<?php

use App\Enums\UserRole;
use App\Models\Resource;
use App\Models\ResourceFolder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

test('owner can store a video link with a thumbnail url', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Video With Thumbnail',
        'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/0.jpg',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(201);

    $resource = Resource::first();
    expect($resource->thumbnail_url)->toBe('https://img.youtube.com/vi/dQw4w9WgXcQ/0.jpg');
});

test('thumbnail url is optional when creating a video link', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Video Without Thumbnail',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(201);

    $resource = Resource::first();
    expect($resource->thumbnail_url)->toBeNull();
});

test('owner can update thumbnail url on a video link resource', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $resource = Resource::create([
        'resource_folder_id' => $folder->id,
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Original Title',
    ]);

    $this->actingAs($user);

    $response = $this->putJson("/resources/files/{$resource->id}", [
        'title' => 'Updated Title',
        'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
    ]);

    $response->assertOk();

    $resource->refresh();
    expect($resource->thumbnail_url)->toBe('https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg')
        ->and($resource->title)->toBe('Updated Title');
});

test('thumbnail url must be a valid url', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $response = $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Bad Thumbnail',
        'thumbnail_url' => 'not-a-valid-url',
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['thumbnail_url']);
});
