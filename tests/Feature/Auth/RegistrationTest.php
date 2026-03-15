<?php

use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    Team::factory()->create([
        'id' => 1,
        'name' => 'Drive',
    ]);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('new users are assigned current_team_id of 1 during registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Team Test User',
        'email' => 'teamtest@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'teamtest@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->current_team_id)->toBe(1)
        ->and($user->team->name)->toBe('Drive');
});

test('registered user belongs to a team via relationship', function () {
    $this->post(route('register.store'), [
        'name' => 'Relationship User',
        'email' => 'relationship@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'relationship@example.com')->first();

    expect($user->team)->toBeInstanceOf(Team::class)
        ->and($user->team->id)->toBe(1);
});
