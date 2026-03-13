<?php

use App\Actions\Instructor\CreateCalendarItemAction;
use App\Models\Instructor;
use App\Models\User;

test('calendar items can be created with 15-minute increment start times', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);

    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '09:15',
        endTime: '11:15',
        isAvailable: true,
    );

    expect($item->start_time)->toBe('09:15:00');
    expect($item->end_time)->toBe('11:15:00');
    expect($item->is_available)->toBeTrue();
});

test('calendar items can be created at :45 minute start times', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);

    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '14:45',
        endTime: '16:45',
        isAvailable: true,
    );

    expect($item->start_time)->toBe('14:45:00');
    expect($item->end_time)->toBe('16:45:00');
});

test('store calendar item endpoint accepts 15-minute increment start times', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '10:15',
        'end_time' => '12:15',
        'is_available' => true,
    ]);

    $response->assertCreated();
    expect($response->json('data.start_time'))->toBe('10:15:00');
    expect($response->json('data.end_time'))->toBe('12:15:00');
});

test('store calendar item endpoint accepts :45 minute start times', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '08:45',
        'end_time' => '10:45',
        'is_available' => true,
    ]);

    $response->assertCreated();
    expect($response->json('data.start_time'))->toBe('08:45:00');
    expect($response->json('data.end_time'))->toBe('10:45:00');
});

test('store calendar item with 15-minute start time and travel time creates correct travel block', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '09:15',
        'end_time' => '11:15',
        'is_available' => true,
        'travel_time_minutes' => 15,
    ]);

    $response->assertCreated();
    expect($response->json('data.start_time'))->toBe('09:15:00');
    expect($response->json('data.travel_time_minutes'))->toBe(15);
});
