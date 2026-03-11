<?php

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->instructor = Instructor::factory()->create();
    $this->futureDate = Carbon::tomorrow()->format('Y-m-d');
});

// ── Block Duration Validation ─────────────────────────────

it('allows creating a valid 2-hour block', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(201);
    $response->assertJsonPath('calendar_item.start_time', '09:00');
    $response->assertJsonPath('calendar_item.end_time', '11:00');
});

it('rejects blocks that are not exactly 2 hours', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('end_time');
});

it('rejects blocks longer than 2 hours', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('end_time');
});

it('allows 30-minute aligned start times', function () {
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '09:30',
            'end_time' => '11:30',
            'is_available' => true,
        ]
    );

    $response->assertStatus(201);
    $response->assertJsonPath('calendar_item.start_time', '09:30');
    $response->assertJsonPath('calendar_item.end_time', '11:30');
});

// ── Travel Time Validation ────────────────────────────────

it('rejects a new slot that violates 30-min travel time after an existing slot', function () {
    // Create existing slot: 09:00 - 11:00
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Try to create slot at 11:00 - 13:00 (only 0 min gap - needs 30)
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '11:00',
            'end_time' => '13:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('start_time');
});

it('rejects a new slot that starts within 30 min of an existing slot ending', function () {
    // Create existing slot: 09:00 - 11:00
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Try 11:00 - 13:00 (0 min gap, needs 30)
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '11:00',
            'end_time' => '13:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('start_time');
});

it('allows a new slot with exactly 30 min gap after an existing slot', function () {
    // Create existing slot: 09:00 - 11:00
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Create slot at 11:30 - 13:30 (exactly 30 min gap)
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '11:30',
            'end_time' => '13:30',
            'is_available' => true,
        ]
    );

    $response->assertStatus(201);
});

it('allows a new slot with more than 30 min gap after an existing slot', function () {
    // Create existing slot: 09:00 - 11:00
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Create slot at 12:00 - 14:00 (60 min gap)
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(201);
});

it('rejects a new slot that violates travel time before an existing slot', function () {
    // Create existing slot: 12:00 - 14:00
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '12:00',
        'end_time' => '14:00',
        'is_available' => true,
    ]);

    // Try 10:00 - 12:00 (ends right when existing starts - 0 min gap)
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('start_time');
});

it('allows a new slot with 30 min gap before an existing slot', function () {
    // Create existing slot: 12:00 - 14:00
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '12:00',
        'end_time' => '14:00',
        'is_available' => true,
    ]);

    // Create slot at 09:30 - 11:30 (30 min gap before existing starts at 12:00)
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $this->futureDate,
            'start_time' => '09:30',
            'end_time' => '11:30',
            'is_available' => true,
        ]
    );

    $response->assertStatus(201);
});

// ── Update Validation ─────────────────────────────────────

it('enforces 2-hour blocks on update', function () {
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    $item = CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    $response = $this->actingAs($this->user)->putJson(
        route('instructors.calendar.items.update', [$this->instructor, $item]),
        [
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:30',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('end_time');
});

it('enforces travel time on update', function () {
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);

    // First slot: 09:00 - 11:00
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Second slot: 14:00 - 16:00
    $itemToMove = CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '14:00',
        'end_time' => '16:00',
        'is_available' => true,
    ]);

    // Try to move second slot to 11:00 - 13:00 (0 min gap from first)
    $response = $this->actingAs($this->user)->putJson(
        route('instructors.calendar.items.update', [$this->instructor, $itemToMove]),
        [
            'date' => $this->futureDate,
            'start_time' => '11:00',
            'end_time' => '13:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('start_time');
});

it('allows different dates without travel time conflict', function () {
    $calendar = Calendar::create([
        'instructor_id' => $this->instructor->id,
        'date' => $this->futureDate,
    ]);
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Same time, different date = no conflict
    $nextDay = Carbon::parse($this->futureDate)->addDay()->format('Y-m-d');
    $response = $this->actingAs($this->user)->postJson(
        route('instructors.calendar.items.store', $this->instructor),
        [
            'date' => $nextDay,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_available' => true,
        ]
    );

    $response->assertStatus(201);
});
