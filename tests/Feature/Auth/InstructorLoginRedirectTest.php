<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\User;

test('instructors are redirected to their instructor page after login', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('instructors.show', $instructor));
});

test('non-instructor users are redirected to dashboard after login', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
