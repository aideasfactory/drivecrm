<?php

use App\Models\Instructor;
use App\Models\User;

test('an instructor can be created with manual transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'test-manual@example.com',
        'transmission_type' => 'manual',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'transmission_type' => 'manual',
    ]);
});

test('an instructor can be created with automatic transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'test-auto@example.com',
        'transmission_type' => 'automatic',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'transmission_type' => 'automatic',
    ]);
});

test('an instructor can be created with both transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'test-both@example.com',
        'transmission_type' => 'both',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'transmission_type' => 'both',
    ]);
});

test('an instructor cannot be created with an invalid transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'test-invalid@example.com',
        'transmission_type' => 'invalid',
    ]);

    $response->assertSessionHasErrors('transmission_type');
});

test('an instructor can be updated to both transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create([
        'transmission_type' => 'manual',
    ]);

    $response = $this->put(route('instructors.update', $instructor), [
        'name' => $instructor->user->name,
        'email' => $instructor->user->email,
        'transmission_type' => 'both',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'id' => $instructor->id,
        'transmission_type' => 'both',
    ]);
});

test('updating an instructor profile preserves the existing transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create([
        'transmission_type' => 'automatic',
    ]);

    $response = $this->put(route('instructors.update', $instructor), [
        'name' => $instructor->user->name,
        'email' => $instructor->user->email,
        'transmission_type' => 'automatic',
        'bio' => 'Updated bio text',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'id' => $instructor->id,
        'transmission_type' => 'automatic',
        'bio' => 'Updated bio text',
    ]);
});

test('an instructor cannot be updated with an invalid transmission type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create([
        'transmission_type' => 'manual',
    ]);

    $response = $this->put(route('instructors.update', $instructor), [
        'name' => $instructor->user->name,
        'email' => $instructor->user->email,
        'transmission_type' => 'invalid',
    ]);

    $response->assertSessionHasErrors('transmission_type');
});
