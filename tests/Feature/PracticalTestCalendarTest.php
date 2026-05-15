<?php

use App\Actions\Instructor\CreateCalendarItemAction;
use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;
use App\Services\CalendarService;
use Carbon\Carbon;

test('creating a practical test slot calculates correct time block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '11:00',
        isPracticalTest: true
    );

    expect($item->item_type)->toBe(CalendarItemType::PracticalTest);
    expect($item->is_available)->toBeFalse();
    expect($item->unavailability_reason)->toBe('Practical Test');

    // Full block: 1hr prep (09:00) + 1hr test (10:00-11:00) + 30min buffer (11:30)
    expect($item->start_time)->toBe('09:00:00');
    expect($item->end_time)->toBe('11:30:00');
});

test('practical test slot with custom notes preserves them', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '14:00',
        endTime: '15:00',
        notes: 'Test centre in Durham',
        isPracticalTest: true
    );

    expect($item->item_type)->toBe(CalendarItemType::PracticalTest);
    expect($item->notes)->toBe('Test centre in Durham');
    expect($item->start_time)->toBe('13:00:00');
    expect($item->end_time)->toBe('15:30:00');
});

test('practical test slot does not create travel block', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '11:00',
        travelTimeMinutes: 30,
        isPracticalTest: true
    );

    // Should be practical test, not a slot with travel
    expect($item->item_type)->toBe(CalendarItemType::PracticalTest);

    // No travel block should exist
    $travelItem = CalendarItem::where('parent_item_id', $item->id)->first();
    expect($travelItem)->toBeNull();
});

test('practical test slot is excluded from booking availability', function () {
    $instructor = Instructor::factory()->create();

    // Create a practical test slot
    $action = app(CreateCalendarItemAction::class);
    $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '11:00',
        isPracticalTest: true
    );

    // Also create a normal available slot
    $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '14:00',
        endTime: '16:00',
        isAvailable: true
    );

    $calendarService = app(CalendarService::class);
    $availability = $calendarService->getAvailability(
        $instructor->id,
        now()->addDays(3),
        Carbon::parse('2026-04-07')
    );

    // Find the date entry for 2026-04-01
    $dateEntry = collect($availability['dates'])->firstWhere('date', '2026-04-01');

    if ($dateEntry) {
        // Only the normal slot should appear, not the practical test
        $slotIds = collect($dateEntry['slots'])->pluck('id')->toArray();
        $practicalTestItem = CalendarItem::where('item_type', 'practical_test')
            ->whereHas('calendar', fn ($q) => $q->where('instructor_id', $instructor->id))
            ->first();

        expect($slotIds)->not->toContain($practicalTestItem->id);
    }
});

test('CalendarItem isPracticalTest method returns correct value', function () {
    $slotItem = CalendarItem::factory()->create(['item_type' => 'slot']);
    $travelItem = CalendarItem::factory()->create(['item_type' => 'travel', 'is_available' => false]);
    $practicalTestItem = CalendarItem::factory()->practicalTest()->create();

    expect($slotItem->isPracticalTest())->toBeFalse();
    expect($travelItem->isPracticalTest())->toBeFalse();
    expect($practicalTestItem->isPracticalTest())->toBeTrue();
});

test('deleting a practical test slot succeeds', function () {
    $instructor = Instructor::factory()->create();

    $action = app(CreateCalendarItemAction::class);
    $item = $action(
        instructor: $instructor,
        date: '2026-04-01',
        startTime: '10:00',
        endTime: '11:00',
        isPracticalTest: true
    );

    $itemId = $item->id;

    $deleteAction = app(DeleteCalendarItemAction::class);
    $deleteAction($item);

    expect(CalendarItem::find($itemId))->toBeNull();
});

test('API endpoint creates practical test slot', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => '2026-04-15',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'is_practical_test' => true,
    ]);

    $response->assertCreated();

    $calendarItem = CalendarItem::where('item_type', 'practical_test')
        ->whereHas('calendar', fn ($q) => $q->where('instructor_id', $instructor->id))
        ->first();

    expect($calendarItem)->not->toBeNull();
    expect($calendarItem->is_available)->toBeFalse();
    expect($calendarItem->start_time)->toBe('09:00:00');
    expect($calendarItem->end_time)->toBe('11:30:00');
    expect($calendarItem->unavailability_reason)->toBe('Practical Test');
});

test('API endpoint does not require unavailability_reason for practical test', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->postJson(route('instructors.calendar.items.store', $instructor), [
        'date' => '2026-04-15',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'is_practical_test' => true,
    ]);

    // Should succeed without unavailability_reason
    $response->assertCreated();
});

test('calendar response includes practical_test item_type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-01',
    ]);

    CalendarItem::factory()->practicalTest()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00:00',
        'end_time' => '11:30:00',
    ]);

    $response = $this->getJson(route('instructors.calendar', [
        'instructor' => $instructor->id,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-07',
    ]));

    $response->assertOk();

    $items = $response->json('calendar.0.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['item_type'])->toBe('practical_test');
});
