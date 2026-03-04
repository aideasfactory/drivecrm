<?php

use App\Models\Instructor;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('instructors.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view the instructors index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('instructors.index'));

    $response->assertOk();
    $response->assertInertiaComponent('Instructors/Index');
});

test('instructors index returns instructor data with connection status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Instructor::factory()->stripeConnected()->create();
    Instructor::factory()->create();

    $response = $this->get(route('instructors.index'));

    $response->assertOk();
    $response->assertInertiaComponent('Instructors/Index');
    $response->assertInertiaProp('instructors', function ($instructors) {
        expect($instructors)->toHaveCount(2);

        $connected = collect($instructors)->firstWhere('connection_status', 'connected');
        $notConnected = collect($instructors)->firstWhere('connection_status', 'not_connected');

        expect($connected)->not->toBeNull();
        expect($notConnected)->not->toBeNull();
    });
});
