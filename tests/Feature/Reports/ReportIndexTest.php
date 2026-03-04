<?php

use App\Enums\CalendarItemStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('reports.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view the reports page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertiaComponent('Reports/Index');
});

test('reports page returns analytics data with instructor metrics', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $calendar = Calendar::create([
        'instructor_id' => $instructor->id,
        'date' => now()->format('Y-m-d'),
    ]);

    // 3 available slots
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'status' => CalendarItemStatus::DRAFT,
    ]);

    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00',
        'end_time' => '11:00',
        'is_available' => true,
        'status' => CalendarItemStatus::BOOKED,
    ]);

    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '11:00',
        'end_time' => '12:00',
        'is_available' => true,
        'status' => CalendarItemStatus::COMPLETED,
    ]);

    // 1 unavailable slot (should not count as available)
    CalendarItem::create([
        'calendar_id' => $calendar->id,
        'start_time' => '13:00',
        'end_time' => '14:00',
        'is_available' => false,
        'status' => null,
    ]);

    $response = $this->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertiaProp('analytics.instructors', function ($instructors) {
        expect($instructors)->toHaveCount(1);

        $instructor = $instructors[0];
        expect($instructor['total_available'])->toBe(3);
        expect($instructor['total_booked'])->toBe(2);
        expect($instructor['total_free'])->toBe(1);
        expect($instructor['utilization_rate'])->toBe(66.7);
    });

    $response->assertInertiaProp('analytics.summary', function ($summary) {
        expect($summary['total_available'])->toBe(3);
        expect($summary['total_booked'])->toBe(2);
        expect($summary['total_free'])->toBe(1);
        expect($summary['overall_utilization'])->toBe(66.7);
    });
});

test('reports page handles no instructors gracefully', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertiaProp('analytics.instructors', function ($instructors) {
        expect($instructors)->toHaveCount(0);
    });

    $response->assertInertiaProp('analytics.summary', function ($summary) {
        expect($summary['total_available'])->toBe(0);
        expect($summary['total_booked'])->toBe(0);
        expect($summary['total_free'])->toBe(0);
        expect($summary['overall_utilization'])->toBe(0.0);
    });
});

test('reports page only includes active instructors', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $activeInstructor = Instructor::factory()->create(['status' => 'active']);
    $inactiveInstructor = Instructor::factory()->create(['status' => 'inactive']);

    $activeCalendar = Calendar::create([
        'instructor_id' => $activeInstructor->id,
        'date' => now()->format('Y-m-d'),
    ]);

    CalendarItem::create([
        'calendar_id' => $activeCalendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'status' => CalendarItemStatus::DRAFT,
    ]);

    $inactiveCalendar = Calendar::create([
        'instructor_id' => $inactiveInstructor->id,
        'date' => now()->format('Y-m-d'),
    ]);

    CalendarItem::create([
        'calendar_id' => $inactiveCalendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'status' => CalendarItemStatus::BOOKED,
    ]);

    $response = $this->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertiaProp('analytics.instructors', function ($instructors) {
        expect($instructors)->toHaveCount(1);
        expect($instructors[0]['total_available'])->toBe(1);
    });
});
