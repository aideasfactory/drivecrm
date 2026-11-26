<?php

use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;

/**
 * A booked 10:00-12:00 lesson with a 30-minute travel block (12:00-12:30).
 *
 * @return array{instructor: Instructor, calendar: Calendar, lesson: CalendarItem, travel: CalendarItem}
 */
function bookedLessonWithTravel(): array
{
    $instructor = Instructor::factory()->create();
    $calendar = Calendar::factory()->create(['instructor_id' => $instructor->id, 'date' => '2026-12-01']);

    $lesson = CalendarItem::factory()->booked()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'travel_time_minutes' => 30,
    ]);

    $travel = CalendarItem::factory()->travel()->create([
        'calendar_id' => $calendar->id,
        'parent_item_id' => $lesson->id,
        'start_time' => '12:00:00',
        'end_time' => '12:30:00',
    ]);

    return compact('instructor', 'calendar', 'lesson', 'travel');
}

function travelTimeUrl(Instructor $instructor, CalendarItem $item): string
{
    return route('instructors.calendar.items.travel-time.update', ['instructor' => $instructor, 'calendarItem' => $item]);
}

test('an owner can change the travel time after a booked lesson', function () {
    ['instructor' => $instructor, 'lesson' => $lesson, 'travel' => $travel] = bookedLessonWithTravel();
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]));

    $this->putJson(travelTimeUrl($instructor, $travel), ['travel_time_minutes' => 60])->assertOk();

    expect($travel->fresh()->end_time)->toBe('13:00:00')
        ->and($lesson->fresh()->travel_time_minutes)->toBe(60)
        ->and($lesson->fresh()->status->value)->toBe('booked');
});

test('an owner can remove the travel time after a booked lesson', function () {
    ['instructor' => $instructor, 'lesson' => $lesson, 'travel' => $travel] = bookedLessonWithTravel();
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]));

    $this->putJson(travelTimeUrl($instructor, $travel), ['travel_time_minutes' => 0])->assertOk();

    expect(CalendarItem::find($travel->id))->toBeNull()
        ->and($lesson->fresh())->not->toBeNull()
        ->and($lesson->fresh()->travel_time_minutes)->toBeNull();
});

test('an owner can add travel time from the lesson itself', function () {
    ['instructor' => $instructor, 'lesson' => $lesson, 'travel' => $travel] = bookedLessonWithTravel();
    $travel->delete();
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]));

    $this->putJson(travelTimeUrl($instructor, $lesson), ['travel_time_minutes' => 15])->assertOk();

    $newTravel = $lesson->fresh()->travelItem;
    expect($newTravel)->not->toBeNull()
        ->and($newTravel->start_time)->toBe('12:00:00')
        ->and($newTravel->end_time)->toBe('12:15:00');
});

test('longer travel time cannot run into the next diary item', function () {
    ['instructor' => $instructor, 'calendar' => $calendar, 'travel' => $travel] = bookedLessonWithTravel();
    CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '12:45:00',
        'end_time' => '14:45:00',
    ]);
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]));

    $this->putJson(travelTimeUrl($instructor, $travel), ['travel_time_minutes' => 60])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['travel_time_minutes']);

    expect($travel->fresh()->end_time)->toBe('12:30:00');
});

test('travel time must be one of the standard lengths', function () {
    ['instructor' => $instructor, 'travel' => $travel] = bookedLessonWithTravel();
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]));

    $this->putJson(travelTimeUrl($instructor, $travel), ['travel_time_minutes' => 20])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['travel_time_minutes']);
});

test('instructors cannot use the owner travel time endpoint', function () {
    ['instructor' => $instructor, 'travel' => $travel] = bookedLessonWithTravel();
    $instructor->user->update(['role' => UserRole::INSTRUCTOR]);
    $this->actingAs($instructor->user);

    $this->putJson(travelTimeUrl($instructor, $travel), ['travel_time_minutes' => 0])->assertForbidden();

    expect(CalendarItem::find($travel->id))->not->toBeNull();
});

test('a travel block from another instructor is not found', function () {
    ['travel' => $travel] = bookedLessonWithTravel();
    $otherInstructor = Instructor::factory()->create();
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]));

    $this->putJson(travelTimeUrl($otherInstructor, $travel), ['travel_time_minutes' => 0])->assertNotFound();
});
