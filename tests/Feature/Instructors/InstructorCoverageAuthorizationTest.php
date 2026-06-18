<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Coverage area mutations (add / delete) and CSV import/export must only be
 * performed by admin (Owner) users. Instructors may still GET their own
 * coverage list to view it in the admin Coverage tab.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->instructorUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $this->instructor = Instructor::factory()->create(['user_id' => $this->instructorUser->id]);
});

it('lets an owner add a coverage area', function () {
    $this->actingAs($this->owner);

    $response = $this->postJson(route('instructors.locations.store', $this->instructor), [
        'postcode_sector' => 'TS7',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('locations', [
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'TS7',
    ]);
});

it('blocks an instructor from adding a coverage area to their own profile', function () {
    $this->actingAs($this->instructorUser);

    $response = $this->postJson(route('instructors.locations.store', $this->instructor), [
        'postcode_sector' => 'TS7',
    ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('locations', [
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'TS7',
    ]);
});

it('lets an owner delete a coverage area', function () {
    $this->actingAs($this->owner);

    $location = Location::create([
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'WR14',
    ]);

    $response = $this->deleteJson(route('instructors.locations.destroy', [
        'instructor' => $this->instructor,
        'location' => $location,
    ]));

    $response->assertOk();

    $this->assertDatabaseMissing('locations', ['id' => $location->id]);
});

it('blocks an instructor from deleting their own coverage area', function () {
    $location = Location::create([
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'WR14',
    ]);

    $this->actingAs($this->instructorUser);

    $response = $this->deleteJson(route('instructors.locations.destroy', [
        'instructor' => $this->instructor,
        'location' => $location,
    ]));

    $response->assertForbidden();

    $this->assertDatabaseHas('locations', ['id' => $location->id]);
});

it('lets an owner download the coverage CSV', function () {
    Location::create([
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'M1',
    ]);

    $this->actingAs($this->owner);

    $response = $this->get(route('instructors.locations.export', $this->instructor));

    $response->assertOk();
});

it('blocks an instructor from downloading the coverage CSV', function () {
    Location::create([
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'M1',
    ]);

    $this->actingAs($this->instructorUser);

    $response = $this->get(route('instructors.locations.export', $this->instructor));

    $response->assertForbidden();
});

it('lets an owner upload a coverage CSV', function () {
    $this->actingAs($this->owner);

    $csv = "postcode_sector\nTS7\nWR14\n";
    $file = UploadedFile::fake()->createWithContent('coverage.csv', $csv);

    $response = $this->post(route('instructors.locations.import', $this->instructor), [
        'file' => $file,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('locations', [
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'TS7',
    ]);
});

it('blocks an instructor from uploading a coverage CSV', function () {
    $this->actingAs($this->instructorUser);

    $csv = "postcode_sector\nTS7\n";
    $file = UploadedFile::fake()->createWithContent('coverage.csv', $csv);

    $response = $this->post(route('instructors.locations.import', $this->instructor), [
        'file' => $file,
    ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('locations', [
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'TS7',
    ]);
});

it('lets an instructor read their own coverage areas list', function () {
    Location::create([
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'TS7',
    ]);

    $this->actingAs($this->instructorUser);

    $response = $this->getJson(route('instructors.locations', $this->instructor));

    $response->assertOk();
    $response->assertJsonStructure(['locations']);
});

it('lets an owner read any instructor coverage areas list', function () {
    Location::create([
        'instructor_id' => $this->instructor->id,
        'postcode_sector' => 'TS7',
    ]);

    $this->actingAs($this->owner);

    $response = $this->getJson(route('instructors.locations', $this->instructor));

    $response->assertOk();
    $response->assertJsonStructure(['locations']);
});
