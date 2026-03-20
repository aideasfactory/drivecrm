<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

test('team settings page is displayed for owners', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('team-settings.edit'));

    $response->assertOk();
});

test('team settings page is not accessible to non-owners', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('team-settings.edit'));

    $response->assertForbidden();
});

test('owner can update team primary colour', function () {
    $team = Team::factory()->create([
        'settings' => ['default_slot_duration_minutes' => 120],
    ]);
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'primary_color' => '#FF5733',
            'default_slot_duration_minutes' => 120,
        ]);

    $response->assertRedirect(route('team-settings.edit'));

    $team->refresh();
    expect($team->getPrimaryColor())->toBe('#FF5733');
});

test('owner can update default slot duration', function () {
    $team = Team::factory()->create([
        'settings' => ['default_slot_duration_minutes' => 120],
    ]);
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'default_slot_duration_minutes' => 90,
        ]);

    $response->assertRedirect(route('team-settings.edit'));

    $team->refresh();
    expect($team->getDefaultSlotDurationMinutes())->toBe(90);
});

test('owner can reset primary colour to null', function () {
    $team = Team::factory()->create([
        'settings' => ['primary_color' => '#FF5733'],
    ]);
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'primary_color' => null,
        ]);

    $response->assertRedirect(route('team-settings.edit'));

    $team->refresh();
    expect($team->getPrimaryColor())->toBeNull();
});

test('primary colour must be valid hex', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'primary_color' => 'not-a-colour',
        ]);

    $response->assertSessionHasErrors('primary_color');
});

test('slot duration must be within valid range', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'default_slot_duration_minutes' => 10,
        ]);

    $response->assertSessionHasErrors('default_slot_duration_minutes');
});

test('non-owner cannot update team settings', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'current_team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'primary_color' => '#FF5733',
        ]);

    $response->assertForbidden();
});

test('team settings are preserved when updating individual settings', function () {
    $team = Team::factory()->create([
        'settings' => [
            'primary_color' => '#FF5733',
            'default_slot_duration_minutes' => 90,
            'default_lesson_duration_minutes' => 60,
        ],
    ]);
    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $this
        ->actingAs($user)
        ->put(route('team-settings.update'), [
            'default_slot_duration_minutes' => 120,
        ]);

    $team->refresh();
    expect($team->getPrimaryColor())->toBe('#FF5733')
        ->and($team->getDefaultSlotDurationMinutes())->toBe(120)
        ->and($team->getSetting('default_lesson_duration_minutes'))->toBe(60);
});
