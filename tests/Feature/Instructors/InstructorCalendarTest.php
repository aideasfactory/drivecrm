<?php

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;

test('guests cannot access instructor calendar', function () {
    $instructor = Instructor::factory()->create();

    $response = $this->getJson(route('instructors.calendar', $instructor));

    $response->assertUnauthorized();
});

test('authenticated users can fetch calendar items for a weekly range', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-03-16',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'is_available' => true,
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'is_available' => false,
        'unavailability_reason' => 'Holiday',
    ]);

    $response = $this->getJson(route('instructors.calendar', [
        'instructor' => $instructor->id,
        'start_date' => '2026-03-16',
        'end_date' => '2026-03-22',
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'calendar');
    $response->assertJsonCount(2, 'calendar.0.items');
});

test('authenticated users can fetch calendar items for a monthly range', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    // Create items spread across the month
    $dates = ['2026-03-02', '2026-03-10', '2026-03-20', '2026-03-30'];

    foreach ($dates as $date) {
        $calendar = Calendar::factory()->create([
            'instructor_id' => $instructor->id,
            'date' => $date,
        ]);

        CalendarItem::factory()->create([
            'calendar_id' => $calendar->id,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    // Request the full month range (Mon 23 Feb → Sun 5 Apr covers March 2026)
    $response = $this->getJson(route('instructors.calendar', [
        'instructor' => $instructor->id,
        'start_date' => '2026-02-23',
        'end_date' => '2026-04-05',
    ]));

    $response->assertOk();
    $response->assertJsonCount(4, 'calendar');

    // Verify each calendar date has items
    $calendarDates = collect($response->json('calendar'))->pluck('date')->sort()->values()->all();
    expect($calendarDates)->toBe($dates);
});

test('calendar items outside the requested range are not returned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    // Inside range
    $insideCalendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-03-15',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $insideCalendar->id,
    ]);

    // Outside range
    $outsideCalendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-15',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $outsideCalendar->id,
    ]);

    $response = $this->getJson(route('instructors.calendar', [
        'instructor' => $instructor->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'calendar');
    expect($response->json('calendar.0.date'))->toBe('2026-03-15');
});

test('calendar items include correct structure for monthly view consumption', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-03-10',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'is_available' => true,
        'status' => null,
    ]);

    $response = $this->getJson(route('instructors.calendar', [
        'instructor' => $instructor->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]));

    $response->assertOk();

    $item = $response->json('calendar.0.items.0');
    expect($item)->toHaveKeys([
        'id',
        'calendar_id',
        'date',
        'start_time',
        'end_time',
        'is_available',
        'status',
        'student_name',
    ]);
    expect($item['date'])->toBe('2026-03-10');
    expect($item['start_time'])->toBe('08:00:00');
    expect($item['end_time'])->toBe('10:00:00');
    expect($item['is_available'])->toBeTrue();
});
