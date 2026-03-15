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

test('csv import creates resources in the target folder', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Import Folder']);

    $csv = "title,video_url,description,tags\n";
    $csv .= "Test Video,https://www.youtube.com/watch?v=abc123,A test video,\"tag1,tag2\"\n";

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $this->actingAs($user);

    $response = $this->postJson('/resources/import-csv', [
        'file' => $file,
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['imported' => 1]);

    $resource = Resource::first();
    expect($resource->title)->toBe('Test Video')
        ->and($resource->resource_folder_id)->toBe($folder->id);
});

test('csv import creates subfolders from folder column', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Root']);

    $csv = "title,video_url,description,tags,folder\n";
    $csv .= "Video 1,https://www.youtube.com/watch?v=abc123,Desc 1,tag1,Theory/Road Signs\n";
    $csv .= "Video 2,https://www.youtube.com/watch?v=def456,Desc 2,tag2,Theory/Road Signs\n";
    $csv .= "Video 3,https://www.youtube.com/watch?v=ghi789,Desc 3,tag3,Theory/Hazards\n";

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $this->actingAs($user);

    $response = $this->postJson('/resources/import-csv', [
        'file' => $file,
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['imported' => 3]);

    // Theory folder should be created under Root
    $theoryFolder = ResourceFolder::where('name', 'Theory')->where('parent_id', $folder->id)->first();
    expect($theoryFolder)->not->toBeNull();

    // Road Signs and Hazards should be under Theory
    $roadSignsFolder = ResourceFolder::where('name', 'Road Signs')->where('parent_id', $theoryFolder->id)->first();
    $hazardsFolder = ResourceFolder::where('name', 'Hazards')->where('parent_id', $theoryFolder->id)->first();
    expect($roadSignsFolder)->not->toBeNull();
    expect($hazardsFolder)->not->toBeNull();

    // Videos 1 & 2 should be in Road Signs, Video 3 in Hazards
    expect(Resource::where('resource_folder_id', $roadSignsFolder->id)->count())->toBe(2);
    expect(Resource::where('resource_folder_id', $hazardsFolder->id)->count())->toBe(1);
});

test('csv import supports thumbnail url column', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Import Folder']);

    $csv = "title,video_url,description,tags,folder,thumbnail_url\n";
    $csv .= "Thumb Video,https://www.youtube.com/watch?v=abc123,A test,,, https://img.youtube.com/vi/abc123/0.jpg\n";

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $this->actingAs($user);

    $response = $this->postJson('/resources/import-csv', [
        'file' => $file,
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['imported' => 1]);

    $resource = Resource::first();
    expect($resource->thumbnail_url)->toBe('https://img.youtube.com/vi/abc123/0.jpg');
});

test('csv import without folder column imports to target folder', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $folder = ResourceFolder::create(['name' => 'Direct']);

    $csv = "title,video_url\n";
    $csv .= "Simple,https://www.youtube.com/watch?v=abc123\n";

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $this->actingAs($user);

    $response = $this->postJson('/resources/import-csv', [
        'file' => $file,
        'resource_folder_id' => $folder->id,
    ]);

    $response->assertOk();

    $resource = Resource::first();
    expect($resource->resource_folder_id)->toBe($folder->id);
});

test('folders are sorted alphabetically by name', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);

    // Create folders in non-alphabetical order
    ResourceFolder::create(['name' => 'Zebra']);
    ResourceFolder::create(['name' => 'Alpha']);
    ResourceFolder::create(['name' => 'Middle']);

    $this->actingAs($user);

    $response = $this->getJson('/resources/folders/root/contents');

    $response->assertOk();

    $folders = $response->json('folders');
    $names = array_column($folders, 'name');
    expect($names)->toBe(['Alpha', 'Middle', 'Zebra']);
});
