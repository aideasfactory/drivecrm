<?php

declare(strict_types=1);

use App\Enums\InstructorStatus;
use App\Enums\PdiStatus;
use App\Models\Instructor;
use App\Models\User;

test('an instructor can be created with a valid status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'status-valid@example.com',
        'transmission_type' => 'manual',
        'status' => InstructorStatus::Suspended->value,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'status' => 'suspended',
    ]);
});

test('an instructor cannot be created with an invalid status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'status-invalid@example.com',
        'transmission_type' => 'manual',
        'status' => 'banana',
    ]);

    $response->assertSessionHasErrors('status');
});

test('an instructor can be created with a valid pdi_status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'pdi-valid@example.com',
        'transmission_type' => 'manual',
        'pdi_status' => PdiStatus::PdiPart2->value,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'pdi_status' => 'pdi_part_2',
    ]);
});

test('an instructor cannot be created with an invalid pdi_status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'pdi-invalid@example.com',
        'transmission_type' => 'manual',
        'pdi_status' => 'pdi_part_99',
    ]);

    $response->assertSessionHasErrors('pdi_status');
});

test('status and pdi_status remain optional on create', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('instructors.store'), [
        'name' => 'Test Instructor',
        'email' => 'no-status@example.com',
        'transmission_type' => 'manual',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors(['status', 'pdi_status']);
});

test('an instructor can be updated with a valid status and pdi_status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create([
        'status' => InstructorStatus::Active->value,
        'pdi_status' => null,
    ]);

    $response = $this->put(route('instructors.update', $instructor), [
        'name' => $instructor->user->name,
        'email' => $instructor->user->email,
        'transmission_type' => 'manual',
        'status' => InstructorStatus::OnLeave->value,
        'pdi_status' => PdiStatus::Trainee->value,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('instructors', [
        'id' => $instructor->id,
        'status' => 'on_leave',
        'pdi_status' => 'trainee',
    ]);
});

test('an instructor cannot be updated with an invalid status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create([
        'status' => InstructorStatus::Active->value,
    ]);

    $response = $this->put(route('instructors.update', $instructor), [
        'name' => $instructor->user->name,
        'email' => $instructor->user->email,
        'transmission_type' => 'manual',
        'status' => 'gibberish',
    ]);

    $response->assertSessionHasErrors('status');
});

test('an instructor cannot be updated with an invalid pdi_status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->put(route('instructors.update', $instructor), [
        'name' => $instructor->user->name,
        'email' => $instructor->user->email,
        'transmission_type' => 'manual',
        'pdi_status' => 'almost_qualified',
    ]);

    $response->assertSessionHasErrors('pdi_status');
});
