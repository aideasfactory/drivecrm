<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;

test('instructor can be scoped to a team', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $userA = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'current_team_id' => $teamA->id,
    ]);
    Instructor::factory()->create(['user_id' => $userA->id]);

    $userB = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'current_team_id' => $teamB->id,
    ]);
    Instructor::factory()->create(['user_id' => $userB->id]);

    $teamAInstructors = Instructor::forTeam($teamA)->get();
    $teamBInstructors = Instructor::forTeam($teamB)->get();

    expect($teamAInstructors)->toHaveCount(1)
        ->and($teamAInstructors->first()->user_id)->toBe($userA->id)
        ->and($teamBInstructors)->toHaveCount(1)
        ->and($teamBInstructors->first()->user_id)->toBe($userB->id);
});

test('student can be scoped to a team', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $userA = User::factory()->create([
        'role' => UserRole::STUDENT,
        'current_team_id' => $teamA->id,
    ]);
    Student::factory()->create(['user_id' => $userA->id]);

    $userB = User::factory()->create([
        'role' => UserRole::STUDENT,
        'current_team_id' => $teamB->id,
    ]);
    Student::factory()->create(['user_id' => $userB->id]);

    $teamAStudents = Student::forTeam($teamA)->get();

    expect($teamAStudents)->toHaveCount(1)
        ->and($teamAStudents->first()->user_id)->toBe($userA->id);
});

test('forCurrentTeam scope filters by authenticated user team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $user = User::factory()->create([
        'role' => UserRole::OWNER,
        'current_team_id' => $team->id,
    ]);

    $ownInstructor = Instructor::factory()->create([
        'user_id' => User::factory()->create([
            'role' => UserRole::INSTRUCTOR,
            'current_team_id' => $team->id,
        ])->id,
    ]);

    $otherInstructor = Instructor::factory()->create([
        'user_id' => User::factory()->create([
            'role' => UserRole::INSTRUCTOR,
            'current_team_id' => $otherTeam->id,
        ])->id,
    ]);

    $this->actingAs($user);

    $results = Instructor::forCurrentTeam()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($ownInstructor->id);
});

test('team accessor returns team via user relationship', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::INSTRUCTOR,
        'current_team_id' => $team->id,
    ]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);

    expect($instructor->team())->not->toBeNull()
        ->and($instructor->team()->id)->toBe($team->id);
});

test('team model getSetting returns defaults when settings is null', function () {
    $team = Team::factory()->create(['settings' => null]);

    expect($team->getPrimaryColor())->toBeNull()
        ->and($team->getDefaultSlotDurationMinutes())->toBe(120);
});

test('team model getSetting returns stored values', function () {
    $team = Team::factory()->create([
        'settings' => [
            'primary_color' => '#3366CC',
            'default_slot_duration_minutes' => 90,
        ],
    ]);

    expect($team->getPrimaryColor())->toBe('#3366CC')
        ->and($team->getDefaultSlotDurationMinutes())->toBe(90);
});
