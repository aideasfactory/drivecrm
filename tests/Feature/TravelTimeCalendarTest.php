<?php

use App\Actions\Instructor\CreateCalendarItemAction;
use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Actions\Instructor\UpdateCalendarItemAction;
use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;

test('creating a calendar item with travel time also creates a travel block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '12:00',
        isAvailable: true,
        travelTimeMinutes: 30
    );

    expect($item->item_type)->toBe(CalendarItemType::Slot);
    expect($item->travel_time_minutes)->toBe(30);

    // A travel block should have been created
    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->not->toBeNull();
    expect($travelItem->item_type)->toBe(CalendarItemType::Travel);
    expect($travelItem->is_available)->toBeFalse();
    expect($travelItem->start_time)->toBe('12:00:00');
    expect($travelItem->end_time)->toBe('12:30:00');
    expect($travelItem->unavailability_reason)->toBe('Travel time');
});

test('creating a calendar item without travel time does not create a travel block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '12:00',
        isAvailable: true,
        travelTimeMinutes: null
    );

    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->toBeNull();
});

test('creating an unavailable slot with travel time does not create a travel block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '12:00',
        isAvailable: false,
        unavailabilityReason: 'Holiday',
        travelTimeMinutes: 30
    );

    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->toBeNull();
});

test('travel time of 15 minutes creates correct block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '14:00',
        endTime: '16:00',
        isAvailable: true,
        travelTimeMinutes: 15
    );

    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->not->toBeNull();
    expect($travelItem->start_time)->toBe('16:00:00');
    expect($travelItem->end_time)->toBe('16:15:00');
});

test('travel time of 45 minutes creates correct block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '08:00',
        endTime: '10:00',
        isAvailable: true,
        travelTimeMinutes: 45
    );

    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->not->toBeNull();
    expect($travelItem->start_time)->toBe('10:00:00');
    expect($travelItem->end_time)->toBe('10:45:00');
});

test('deleting a calendar item also deletes its travel block', function () {
    $instructor = Instructor::factory()->create();

    // Create item with travel time
    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '12:00',
        isAvailable: true,
        travelTimeMinutes: 30
    );

    $travelItemId = CalendarItem::where('parent_item_id', $item->id)->value('id');
    expect($travelItemId)->not->toBeNull();

    // Delete the main item
    $deleteAction = app(DeleteCalendarItemAction::class);
    $deleteAction($item);

    expect(CalendarItem::find($item->id))->toBeNull();
    expect(CalendarItem::find($travelItemId))->toBeNull();
});

test('updating a calendar item time also updates its travel block', function () {
    $instructor = Instructor::factory()->create();

    // Create item with travel time
    $createAction = app(CreateCalendarItemAction::class);
    $item = $createAction(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '12:00',
        isAvailable: true,
        travelTimeMinutes: 30
    );

    // Update the item to a new time
    $updateAction = app(UpdateCalendarItemAction::class);
    $updatedItem = $updateAction(
        instructor: $instructor,
        calendarItem: $item,
        date: '2026-04-01',
        startTime: '14:00',
        endTime: '16:00',
    );

    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->not->toBeNull();
    expect($travelItem->start_time)->toBe('16:00:00');
    expect($travelItem->end_time)->toBe('16:30:00');
});

test('CalendarItem isTravel method returns correct value', function () {
    $slotItem = CalendarItem::factory()->create(['item_type' => 'slot']);
    $travelItem = CalendarItem::factory()->create(['item_type' => 'travel', 'is_available' => false]);

    expect($slotItem->isTravel())->toBeFalse();
    expect($travelItem->isTravel())->toBeTrue();
});

test('API endpoint creates slot with travel time via travel_time_minutes parameter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => '2026-04-15',
        'start_time' => '09:30',
        'end_time' => '11:30',
        'is_available' => true,
        'travel_time_minutes' => 30,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('has_travel_item', true);

    // Verify the main slot was created
    $calendarItem = CalendarItem::where('item_type', 'slot')
        ->whereHas('calendar', fn ($q) => $q->where('instructor_id', $instructor->id)->where('date', '2026-04-15'))
        ->first();

    expect($calendarItem)->not->toBeNull();
    expect($calendarItem->start_time)->toBe('09:30:00');
    expect($calendarItem->travel_time_minutes)->toBe(30);

    // Verify travel block
    $travelItem = CalendarItem::where('parent_item_id', $calendarItem->id)->first();
    expect($travelItem)->not->toBeNull();
    expect($travelItem->item_type->value)->toBe('travel');
    expect($travelItem->start_time)->toBe('11:30:00');
    expect($travelItem->end_time)->toBe('12:00:00');
});

test('API validates travel_time_minutes must be 15, 30, or 45', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => '2026-04-15',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'is_available' => true,
        'travel_time_minutes' => 20,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['travel_time_minutes']);
});

test('calendar response includes item_type field', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-01',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'item_type' => 'slot',
    ]);

    CalendarItem::factory()->travel()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '12:00:00',
        'end_time' => '12:30:00',
    ]);

    $response = $this->getJson(route('instructors.calendar', [
        'instructor' => $instructor->id,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-07',
    ]));

    $response->assertOk();

    $items = $response->json('calendar.0.items');
    expect($items)->toHaveCount(2);

    $types = collect($items)->pluck('item_type')->sort()->values()->all();
    expect($types)->toBe(['slot', 'travel']);
});

test('overlap validation includes travel time window', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    // Create an existing slot at 10:00-12:00 with 30-min travel
    $createAction = app(CreateCalendarItemAction::class);
    $createAction(
        instructor: $instructor,
        date: '2026-04-15',
        startTime: '10:00',
        endTime: '12:00',
        isAvailable: true,
        travelTimeMinutes: 30
    );

    // Try to create a slot at 12:00-14:00 (overlaps with travel block 12:00-12:30)
    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => '2026-04-15',
        'start_time' => '12:00',
        'end_time' => '14:00',
        'is_available' => true,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['start_time']);
});

test('flexible start times allow 30-minute increments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    // Create slot at 09:30 (not previously possible)
    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => '2026-04-15',
        'start_time' => '09:30',
        'end_time' => '11:30',
        'is_available' => true,
    ]);

    $response->assertCreated();
    expect($response->json('calendar_item.start_time'))->toBe('09:30:00');
    expect($response->json('calendar_item.end_time'))->toBe('11:30:00');
});
