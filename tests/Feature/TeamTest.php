<?php

use App\Models\Team;
use App\Models\User;

test('team can be created with settings', function () {
    $team = Team::factory()->create([
        'name' => 'Drive',
        'settings' => [
            'default_lesson_duration_minutes' => 60,
        ],
    ]);

    expect($team->name)->toBe('Drive')
        ->and($team->settings)->toBeArray()
        ->and($team->settings['default_lesson_duration_minutes'])->toBe(60)
        ->and($team->uuid)->not->toBeNull();
});

test('team has many users', function () {
    $team = Team::factory()->create();

    User::factory()->count(3)->create([
        'current_team_id' => $team->id,
    ]);

    expect($team->users)->toHaveCount(3);
});

test('team settings column is nullable', function () {
    $team = Team::factory()->create([
        'settings' => null,
    ]);

    expect($team->settings)->toBeNull();
});
