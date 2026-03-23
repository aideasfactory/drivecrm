<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\User;

test('owner users can access the dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('instructor users are redirected from the dashboard to their instructor page', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('instructors.show', $instructor));
});

test('instructor users can access their own instructor page', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('instructors.show', $instructor));
    $response->assertOk();
});

test('instructor users are redirected from the instructors index to their own page', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('instructors.index'));
    $response->assertRedirect(route('instructors.show', $instructor));
});

test('instructor users are redirected from packages to their own page', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('packages.index'));
    $response->assertRedirect(route('instructors.show', $instructor));
});

test('instructor users can access student detail pages', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    // Student routes start with /students/ which should be allowed
    $response = $this->get('/students/1');
    // Should not redirect (may 404 if student doesn't exist, but not a redirect)
    $response->assertStatus(404);
});

test('instructor users can access settings pages', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('profile.edit'));
    $response->assertOk();
});

test('owner users are not affected by the restrict instructor middleware', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($user);

    $response = $this->get(route('packages.index'));
    $response->assertOk();
});

test('instructor id is shared via inertia for instructor users', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('instructors.show', $instructor));
    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['auth']['instructor_id'])->toBe($instructor->id);
});

test('instructor id is null for non-instructor users', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['auth']['instructor_id'])->toBeNull();
});
