<?php

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\RecurrencePattern;
use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/instructor/calendar
|--------------------------------------------------------------------------
*/

test('an instructor can retrieve their calendar items for a date', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $tomorrow = now()->addDay()->format('Y-m-d');

    $calendar = Calendar::create([
        'instructor_id' => $instructor->id,
        'date' => $tomorrow,
    ]);

    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'item_type' => CalendarItemType::Slot,
    ]);

    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '11:00',
        'end_time' => '12:00',
        'is_available' => true,
        'item_type' => CalendarItemType::Slot,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/calendar?date={$tomorrow}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('calendar index requires a date parameter', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/calendar');

    $response->assertUnprocessable();
});

test('unauthenticated user cannot access calendar', function () {
    $response = $this->getJson('/api/v1/instructor/calendar?date=2026-03-25');

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/instructor/calendar/items
|--------------------------------------------------------------------------
*/

test('an instructor can create a single calendar item', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $tomorrow = now()->addDay()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', [
        'date' => $tomorrow,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'notes' => 'Morning slot',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', '10:00')
        ->assertJsonPath('data.is_available', true)
        ->assertJsonPath('data.notes', 'Morning slot')
        ->assertJsonPath('data.date', $tomorrow)
        ->assertJsonPath('data.item_type', 'slot');

    $this->assertDatabaseHas('calendar_items', [
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'is_available' => true,
    ]);
});

test('an instructor can create a calendar item with travel time', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $tomorrow = now()->addDay()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', [
        'date' => $tomorrow,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'travel_time_minutes' => 30,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.travel_time_minutes', 30)
        ->assertJsonPath('has_travel_item', true);
});

test('an instructor can create a recurring calendar item', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $tomorrow = now()->addDay()->format('Y-m-d');
    $endDate = now()->addMonths(2)->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', [
        'date' => $tomorrow,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'recurrence_pattern' => 'weekly',
        'recurrence_end_date' => $endDate,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.recurrence_pattern', 'weekly')
        ->assertJsonStructure(['recurring_count']);

    expect($response->json('recurring_count'))->toBeGreaterThan(1);
});

test('store validates required fields', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'start_time', 'end_time']);
});

test('store rejects dates in the past', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', [
        'date' => '2020-01-01',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

test('store rejects end time before start time', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $tomorrow = now()->addDay()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', [
        'date' => $tomorrow,
        'start_time' => '10:00',
        'end_time' => '09:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['end_time']);
});

test('store requires unavailability reason when not available', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $tomorrow = now()->addDay()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/v1/instructor/calendar/items', [
        'date' => $tomorrow,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => false,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['unavailability_reason']);
});

test('unauthenticated user cannot create calendar items', function () {
    $response = $this->postJson('/api/v1/instructor/calendar/items', [
        'date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $response->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/instructor/calendar/items/{calendarItem}
|--------------------------------------------------------------------------
*/

test('an instructor can delete their own calendar item', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $calendar = Calendar::create([
        'instructor_id' => $instructor->id,
        'date' => now()->addDay()->format('Y-m-d'),
    ]);

    $calendarItem = CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'item_type' => CalendarItemType::Slot,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/instructor/calendar/items/{$calendarItem->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Calendar item removed successfully.');

    $this->assertDatabaseMissing('calendar_items', ['id' => $calendarItem->id]);
});

test('an instructor cannot delete another instructors calendar item', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherUser = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $otherInstructor = Instructor::factory()->create(['user_id' => $otherUser->id]);

    $calendar = Calendar::create([
        'instructor_id' => $otherInstructor->id,
        'date' => now()->addDay()->format('Y-m-d'),
    ]);

    $calendarItem = CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'item_type' => CalendarItemType::Slot,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/instructor/calendar/items/{$calendarItem->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('calendar_items', ['id' => $calendarItem->id]);
});

test('an instructor can delete recurring calendar items with scope future', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $groupId = \Illuminate\Support\Str::uuid()->toString();
    $items = [];

    for ($i = 0; $i < 4; $i++) {
        $date = now()->addDays($i * 7 + 1)->format('Y-m-d');
        $calendar = Calendar::firstOrCreate([
            'instructor_id' => $instructor->id,
            'date' => $date,
        ]);

        $items[] = CalendarItem::create([
            'calendar_id' => $calendar->id,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_available' => true,
            'item_type' => CalendarItemType::Slot,
            'recurrence_pattern' => RecurrencePattern::Weekly,
            'recurrence_group_id' => $groupId,
        ]);
    }

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson("/api/v1/instructor/calendar/items/{$items[1]->id}?scope=future");

    $response->assertOk()
        ->assertJsonStructure(['deleted_count']);

    // First item should still exist, items from [1] onward should be deleted
    $this->assertDatabaseHas('calendar_items', ['id' => $items[0]->id]);
});

test('unauthenticated user cannot delete calendar items', function () {
    $response = $this->deleteJson('/api/v1/instructor/calendar/items/1');

    $response->assertUnauthorized();
});
