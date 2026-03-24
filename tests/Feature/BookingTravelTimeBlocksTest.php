<?php

use App\Actions\Student\Order\CreateDraftCalendarItemsAction;
use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;

test('newly created slots get travel blocks when first existing slot has travel time', function () {
    $instructor = Instructor::factory()->create();

    // Create an existing available slot for week 1 with travel time
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-06',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
        'item_type' => 'slot',
        'travel_time_minutes' => 30,
    ]);

    $action = app(CreateDraftCalendarItemsAction::class);
    $ids = $action(
        instructorId: $instructor->id,
        firstLessonDate: '2026-04-06',
        startTime: '10:00',
        endTime: '12:00',
        lessonsCount: 3
    );

    expect($ids)->toHaveCount(3);

    // Week 2 and 3 slots should have travel blocks created
    for ($i = 1; $i < 3; $i++) {
        $calendarItem = CalendarItem::find($ids[$i]);
        expect($calendarItem->travel_time_minutes)->toBe(30);

        $travelBlock = CalendarItem::where('parent_item_id', $ids[$i])->first();
        expect($travelBlock)->not->toBeNull();
        expect($travelBlock->item_type)->toBe(CalendarItemType::Travel);
        expect($travelBlock->is_available)->toBeFalse();
        expect($travelBlock->start_time)->toBe('12:00:00');
        expect($travelBlock->end_time)->toBe('12:30:00');
        expect($travelBlock->unavailability_reason)->toBe('Travel time');
    }
});

test('no travel blocks created when first slot has no travel time', function () {
    $instructor = Instructor::factory()->create();

    // Create an existing available slot for week 1 WITHOUT travel time
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-06',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
        'item_type' => 'slot',
        'travel_time_minutes' => null,
    ]);

    $action = app(CreateDraftCalendarItemsAction::class);
    $ids = $action(
        instructorId: $instructor->id,
        firstLessonDate: '2026-04-06',
        startTime: '10:00',
        endTime: '12:00',
        lessonsCount: 3
    );

    expect($ids)->toHaveCount(3);

    // No travel blocks should exist for any slot
    $travelBlocks = CalendarItem::whereIn('parent_item_id', $ids)->count();
    expect($travelBlocks)->toBe(0);
});

test('reused existing slots preserve their travel blocks', function () {
    $instructor = Instructor::factory()->create();

    // Create existing available slots for all 3 weeks with travel blocks
    for ($i = 0; $i < 3; $i++) {
        $date = \Carbon\Carbon::parse('2026-04-06')->addWeeks($i);

        $calendar = Calendar::factory()->create([
            'instructor_id' => $instructor->id,
            'date' => $date->toDateString(),
        ]);

        $slot = CalendarItem::factory()->create([
            'calendar_id' => $calendar->id,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'is_available' => true,
            'item_type' => 'slot',
            'travel_time_minutes' => 30,
        ]);

        // Create existing travel block
        CalendarItem::factory()->travel()->create([
            'calendar_id' => $calendar->id,
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'parent_item_id' => $slot->id,
        ]);
    }

    $action = app(CreateDraftCalendarItemsAction::class);
    $ids = $action(
        instructorId: $instructor->id,
        firstLessonDate: '2026-04-06',
        startTime: '10:00',
        endTime: '12:00',
        lessonsCount: 3
    );

    expect($ids)->toHaveCount(3);

    // Each slot should still have exactly one travel block (no duplicates)
    foreach ($ids as $id) {
        $travelBlocks = CalendarItem::where('parent_item_id', $id)->count();
        expect($travelBlocks)->toBe(1);
    }
});

test('travel time of 15 minutes creates correct travel block on generated slots', function () {
    $instructor = Instructor::factory()->create();

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-06',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'is_available' => true,
        'item_type' => 'slot',
        'travel_time_minutes' => 15,
    ]);

    $action = app(CreateDraftCalendarItemsAction::class);
    $ids = $action(
        instructorId: $instructor->id,
        firstLessonDate: '2026-04-06',
        startTime: '14:00',
        endTime: '16:00',
        lessonsCount: 2
    );

    // Second slot (newly created) should have a 15-minute travel block
    $travelBlock = CalendarItem::where('parent_item_id', $ids[1])->first();
    expect($travelBlock)->not->toBeNull();
    expect($travelBlock->start_time)->toBe('16:00:00');
    expect($travelBlock->end_time)->toBe('16:15:00');
});

test('travel time of 45 minutes creates correct travel block on generated slots', function () {
    $instructor = Instructor::factory()->create();

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-06',
    ]);

    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'is_available' => true,
        'item_type' => 'slot',
        'travel_time_minutes' => 45,
    ]);

    $action = app(CreateDraftCalendarItemsAction::class);
    $ids = $action(
        instructorId: $instructor->id,
        firstLessonDate: '2026-04-06',
        startTime: '08:00',
        endTime: '10:00',
        lessonsCount: 2
    );

    // Second slot (newly created) should have a 45-minute travel block
    $travelBlock = CalendarItem::where('parent_item_id', $ids[1])->first();
    expect($travelBlock)->not->toBeNull();
    expect($travelBlock->start_time)->toBe('10:00:00');
    expect($travelBlock->end_time)->toBe('10:45:00');
});

test('mixed reused and new slots get correct travel blocks', function () {
    $instructor = Instructor::factory()->create();

    // Create existing slot only for week 1 and week 3 (week 2 is missing)
    $week1Calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-06',
    ]);

    $week1Slot = CalendarItem::factory()->create([
        'calendar_id' => $week1Calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
        'item_type' => 'slot',
        'travel_time_minutes' => 30,
    ]);

    // Existing travel block for week 1
    CalendarItem::factory()->travel()->create([
        'calendar_id' => $week1Calendar->id,
        'start_time' => '12:00:00',
        'end_time' => '12:30:00',
        'parent_item_id' => $week1Slot->id,
    ]);

    $week3Calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-04-20',
    ]);

    $week3Slot = CalendarItem::factory()->create([
        'calendar_id' => $week3Calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
        'item_type' => 'slot',
        'travel_time_minutes' => 30,
    ]);

    // Existing travel block for week 3
    CalendarItem::factory()->travel()->create([
        'calendar_id' => $week3Calendar->id,
        'start_time' => '12:00:00',
        'end_time' => '12:30:00',
        'parent_item_id' => $week3Slot->id,
    ]);

    $action = app(CreateDraftCalendarItemsAction::class);
    $ids = $action(
        instructorId: $instructor->id,
        firstLessonDate: '2026-04-06',
        startTime: '10:00',
        endTime: '12:00',
        lessonsCount: 3
    );

    expect($ids)->toHaveCount(3);

    // Week 1 (reused): existing travel block preserved
    expect(CalendarItem::where('parent_item_id', $ids[0])->count())->toBe(1);

    // Week 2 (new): travel block created
    $week2Travel = CalendarItem::where('parent_item_id', $ids[1])->first();
    expect($week2Travel)->not->toBeNull();
    expect($week2Travel->item_type)->toBe(CalendarItemType::Travel);
    expect($week2Travel->start_time)->toBe('12:00:00');
    expect($week2Travel->end_time)->toBe('12:30:00');

    // Week 3 (reused): existing travel block preserved (no duplicate)
    expect(CalendarItem::where('parent_item_id', $ids[2])->count())->toBe(1);
});
