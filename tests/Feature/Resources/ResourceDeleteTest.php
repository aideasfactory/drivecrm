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

test('owner can delete a file resource and its s3 file is removed', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    // Upload a file first
    $this->post('/resources/files', [
        'resource_type' => 'file',
        'file' => UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4'),
        'title' => 'Delete Me',
        'resource_folder_id' => $folder->id,
    ]);

    $resource = Resource::first();
    $filePath = $resource->file_path;

    Storage::disk('s3')->assertExists($filePath);

    $response = $this->deleteJson("/resources/files/{$resource->id}");

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Resource deleted successfully.']);

    expect(Resource::count())->toBe(0);
    Storage::disk('s3')->assertMissing($filePath);
});

test('owner can delete a video link resource without s3 errors', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Test Folder']);

    $this->actingAs($user);

    $this->postJson('/resources/files', [
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Delete This Video Link',
        'resource_folder_id' => $folder->id,
    ]);

    $resource = Resource::first();
    expect($resource->file_path)->toBeNull();

    $response = $this->deleteJson("/resources/files/{$resource->id}");

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Resource deleted successfully.']);

    expect(Resource::count())->toBe(0);
});

test('owner can delete a folder and all its contents', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Parent Folder']);
    $childFolder = ResourceFolder::create(['name' => 'Child Folder', 'parent_id' => $folder->id]);

    Resource::create([
        'resource_folder_id' => $childFolder->id,
        'resource_type' => 'video_link',
        'video_url' => 'https://www.youtube.com/watch?v=test',
        'title' => 'Nested Video',
    ]);

    $this->actingAs($user);

    $response = $this->deleteJson("/resources/folders/{$folder->id}");

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Folder deleted successfully.']);

    expect(ResourceFolder::count())->toBe(0);
    expect(Resource::count())->toBe(0);
});
