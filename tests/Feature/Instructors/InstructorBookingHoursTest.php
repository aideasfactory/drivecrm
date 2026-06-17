<?php

use App\Enums\LessonStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-06-17 10:00:00'); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

test('booking_hours returns four weeks in a rolling window', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    $response = $this->get(route('instructors.show', $instructor));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Instructors/Show')
        ->has('instructor.booking_hours.weeks', 4)
        ->where('instructor.booking_hours.weeks.0.label', 'Current Week')
        ->where(
            'instructor.booking_hours.weeks.0.start_date',
            Carbon::now()->startOfWeek()->toDateString()
        )
        ->where(
            'instructor.booking_hours.weeks.3.start_date',
            Carbon::now()->addWeeks(3)->startOfWeek()->toDateString()
        )
        ->has('instructor.booking_hours.weeks.0', fn (Assert $week) => $week
            ->has('label')
            ->has('start_date')
            ->has('end_date')
            ->has('hours')
        )
    );
});

test('booking_hours sums lesson hours into the correct week bucket', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    // 2.5-hour lesson in the current week
    Lesson::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => Carbon::now()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '11:30',
        'status' => LessonStatus::PENDING,
    ]);

    // 1-hour lesson in week 3 (two weeks from now)
    Lesson::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => Carbon::now()->addWeeks(2)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => LessonStatus::PENDING,
    ]);

    $response = $this->get(route('instructors.show', $instructor));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('instructor.booking_hours.weeks.0.hours', 2.5)
        ->where('instructor.booking_hours.weeks.1.hours', 0.0)
        ->where('instructor.booking_hours.weeks.2.hours', 1.0)
        ->where('instructor.booking_hours.weeks.3.hours', 0.0)
    );
});

test('booking_hours excludes cancelled and draft lessons', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    Lesson::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => Carbon::now()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'status' => LessonStatus::CANCELLED,
    ]);

    Lesson::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => Carbon::now()->toDateString(),
        'start_time' => '13:00',
        'end_time' => '14:00',
        'status' => LessonStatus::DRAFT,
    ]);

    $response = $this->get(route('instructors.show', $instructor));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('instructor.booking_hours.weeks.0.hours', 0.0)
        ->where('instructor.booking_hours.weeks.1.hours', 0.0)
        ->where('instructor.booking_hours.weeks.2.hours', 0.0)
        ->where('instructor.booking_hours.weeks.3.hours', 0.0)
    );
});

test('booking_hours excludes lessons outside the four-week window', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->create();

    // Lesson last week — outside the window
    Lesson::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => Carbon::now()->subWeek()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
        'status' => LessonStatus::PENDING,
    ]);

    // Lesson 5 weeks from now — outside the window
    Lesson::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => Carbon::now()->addWeeks(5)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
        'status' => LessonStatus::PENDING,
    ]);

    $response = $this->get(route('instructors.show', $instructor));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('instructor.booking_hours.weeks.0.hours', 0.0)
        ->where('instructor.booking_hours.weeks.1.hours', 0.0)
        ->where('instructor.booking_hours.weeks.2.hours', 0.0)
        ->where('instructor.booking_hours.weeks.3.hours', 0.0)
    );
});
