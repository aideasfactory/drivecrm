<?php

use App\Actions\Instructor\CreateCalendarItemAction;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;

test('unavailable calendar item can be created without a reason via action', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);

    $item = $action(
        instructor: $instructor,
        date: now()->addDays(3)->format('Y-m-d'),
        startTime: '09:00',
        endTime: '11:00',
        isAvailable: false,
    );

    expect($item->is_available)->toBeFalse();
    expect($item->unavailability_reason)->toBeNull();
});

test('unavailable calendar item can be created with a reason via action', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);

    $item = $action(
        instructor: $instructor,
        date: now()->addDays(3)->format('Y-m-d'),
        startTime: '09:00',
        endTime: '11:00',
        isAvailable: false,
        unavailabilityReason: 'Doctor appointment',
    );

    expect($item->is_available)->toBeFalse();
    expect($item->unavailability_reason)->toBe('Doctor appointment');
});

test('store endpoint allows unavailable calendar item without a reason', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => false,
    ]);

    $response->assertCreated();
    expect($response->json('data.is_available'))->toBeFalse();
});

test('store endpoint allows unavailable calendar item with a reason', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => false,
        'unavailability_reason' => 'Holiday',
    ]);

    $response->assertCreated();
    expect($response->json('data.is_available'))->toBeFalse();
    expect($response->json('data.unavailability_reason'))->toBe('Holiday');
});

test('update endpoint allows removing unavailability reason', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => now()->addDays(3)->format('Y-m-d'),
    ]);
    $item = CalendarItem::factory()->unavailable()->create([
        'calendar_id' => $calendar->id,
    ]);

    $response = $this->putJson(route('instructors.calendar.items.update', [$instructor, $item]), [
        'date' => $calendar->date->format('Y-m-d'),
        'start_time' => $item->start_time->format('H:i'),
        'end_time' => $item->end_time->format('H:i'),
        'is_available' => false,
        'unavailability_reason' => null,
    ]);

    $response->assertOk();
});
