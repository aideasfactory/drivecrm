<?php

declare(strict_types=1);

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Covers the "Sam tries to add himself as an instructor" sign-up flow.
 *
 * Before the fix, the controller silently discarded `createInstructor()`'s
 * failure array and redirected to the index — pressing "Create Instructor"
 * appeared to do nothing. These tests pin the new behaviour: every failure
 * mode surfaces a 422 with a specific message that the form can display.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
});

it('blocks self-add when the email already belongs to a user', function () {
    $existing = User::factory()->create(['email' => 'sam@aideasfactory.io']);

    Http::fake([
        'api.postcodes.io/postcodes/*' => Http::response([
            'status' => 200,
            'result' => ['latitude' => 53.4808, 'longitude' => -2.2426],
        ]),
    ]);

    $response = $this->from(route('instructors.index'))->post(route('instructors.store'), [
        'name' => 'Sam',
        'email' => $existing->email,
        'transmission_type' => 'manual',
        'postcode' => 'M1 1AA',
    ]);

    $response->assertSessionHasErrors(['email' => 'This email address is already in use.']);
    expect(Instructor::query()->count())->toBe(0);
});

it('rejects the request when no postcode is supplied', function () {
    $response = $this->from(route('instructors.index'))->post(route('instructors.store'), [
        'name' => 'Sam',
        'email' => 'sam-no-postcode@example.com',
        'transmission_type' => 'manual',
    ]);

    $response->assertSessionHasErrors(['postcode']);
    $this->assertDatabaseMissing('users', ['email' => 'sam-no-postcode@example.com']);
    expect(Instructor::query()->count())->toBe(0);
});

it('surfaces a 422 with a postcode message when postcodes.io cannot resolve the postcode', function () {
    Http::fake([
        'api.postcodes.io/postcodes/*' => Http::response(['status' => 404], 404),
    ]);

    $response = $this->from(route('instructors.index'))->post(route('instructors.store'), [
        'name' => 'Sam',
        'email' => 'sam-bad-postcode@example.com',
        'transmission_type' => 'manual',
        'postcode' => 'ZZ99 9ZZ',
    ]);

    $response->assertSessionHasErrors('postcode');
    expect(session('errors')->get('postcode')[0])
        ->toContain('could not find coordinates');

    $this->assertDatabaseMissing('users', ['email' => 'sam-bad-postcode@example.com']);
    expect(Instructor::query()->count())->toBe(0);
});

it('creates the instructor and redirects to the index when all data is valid', function () {
    Http::fake([
        'api.postcodes.io/postcodes/*' => Http::response([
            'status' => 200,
            'result' => ['latitude' => 53.4808, 'longitude' => -2.2426],
        ]),
    ]);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Sam',
        'email' => 'sam-ok@example.com',
        'transmission_type' => 'manual',
        'postcode' => 'M1 1AA',
    ]);

    $response->assertRedirect(route('instructors.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', ['email' => 'sam-ok@example.com']);
    expect(Instructor::query()->count())->toBe(1);
});
