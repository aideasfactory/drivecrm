<?php

declare(strict_types=1);

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;

function weekInstructor(): array
{
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);

    return [
        'instructor' => $instructor,
        'token' => $user->createToken('Test Device')->plainTextToken,
    ];
}

function weekSlot(Instructor $instructor, string $date, string $start, array $overrides = []): CalendarItem
{
    $calendar = Calendar::query()
        ->where('instructor_id', $instructor->id)
        ->whereDate('date', $date)
        ->first();

    if ($calendar === null) {
        $calendar = Calendar::factory()->create([
            'instructor_id' => $instructor->id,
            'date' => $date,
        ]);
    }

    return CalendarItem::factory()->create(array_merge([
        'calendar_id' => $calendar->id,
        'start_time' => $start,
        'end_time' => '12:00:00',
        'is_available' => true,
        'status' => null,
        'item_type' => CalendarItemType::Slot,
    ], $overrides));
}

function weekLesson(Instructor $instructor, string $date, string $start, LessonStatus $status = LessonStatus::PENDING): Lesson
{
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $package = Package::factory()->forInstructor($instructor)->create();
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'package_id' => $package->id,
    ]);

    return Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $date,
        'start_time' => $start,
        'end_time' => '11:00',
        'status' => $status,
        'student_lesson_number' => random_int(1, 100000),
    ]);
}

beforeEach(function () {
    $this->mock(StripeService::class, function ($mock) {
        $mock->shouldIgnoreMissing();
    });
});

test('calendar items can be listed for an inclusive date range', function () {
    ['instructor' => $instructor, 'token' => $token] = weekInstructor();

    $monday = weekSlot($instructor, '2026-09-21', '11:00:00', [
        'status' => CalendarItemStatus::DRAFT,
        'is_available' => false,
    ]);
    $travel = weekSlot($instructor, '2026-09-21', '09:00:00', [
        'item_type' => CalendarItemType::Travel,
        'is_available' => false,
        'parent_item_id' => $monday->id,
    ]);
    $sunday = weekSlot($instructor, '2026-09-27', '08:00:00', [
        'status' => CalendarItemStatus::BOOKED,
        'is_available' => false,
    ]);
    weekSlot($instructor, '2026-09-20', '08:00:00');
    weekSlot($instructor, '2026-09-28', '08:00:00');

    $other = Instructor::factory()->create();
    weekSlot($other, '2026-09-22', '08:00:00');

    $response = $this->withToken($token)->getJson(
        '/api/v1/instructor/calendar/items?from=2026-09-21&to=2026-09-27&available_only=0&exclude_drafts=0'
    );

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$travel->id, $monday->id, $sunday->id]);
    expect($response->json('data.0.date'))->toBe('2026-09-21')
        ->and($response->json('data.0.item_type'))->toBe('travel')
        ->and($response->json('data.1.status'))->toBe('draft')
        ->and($response->json('data.2.date'))->toBe('2026-09-27');
});

test('a single calendar date still works when from and to are absent', function () {
    ['instructor' => $instructor, 'token' => $token] = weekInstructor();
    $item = weekSlot($instructor, '2026-09-21', '09:00:00');
    weekSlot($instructor, '2026-09-22', '09:00:00');

    $response = $this->withToken($token)->getJson(
        '/api/v1/instructor/calendar/items?date=2026-09-21&available_only=0&exclude_drafts=0'
    );

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $item->id)
        ->assertJsonPath('data.0.date', '2026-09-21');
});

test('a calendar date request ignores an invalid range', function () {
    ['instructor' => $instructor, 'token' => $token] = weekInstructor();
    weekSlot($instructor, '2026-09-21', '09:00:00');

    $response = $this->withToken($token)->getJson(
        '/api/v1/instructor/calendar/items?date=2026-09-21&from=2026-01-01&to=2026-09-01&available_only=0'
    );

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('calendar range validation rejects a partial or oversized span', function (string $query, string $field) {
    ['token' => $token] = weekInstructor();

    $this->withToken($token)
        ->getJson('/api/v1/instructor/calendar/items?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'only from' => ['from=2026-09-21&available_only=0', 'to'],
    'only to' => ['to=2026-09-27&available_only=0', 'from'],
    'bad format' => ['from=21-09-2026&to=2026-09-27', 'from'],
    'to before from' => ['from=2026-09-27&to=2026-09-21', 'to'],
    'longer than 31 days' => ['from=2026-09-01&to=2026-10-02', 'to'],
]);

test('a 31 day calendar range is accepted and empty days are omitted', function () {
    ['instructor' => $instructor, 'token' => $token] = weekInstructor();
    weekSlot($instructor, '2026-09-01', '09:00:00');

    $this->withToken($token)
        ->getJson('/api/v1/instructor/calendar/items?from=2026-09-01&to=2026-10-01&available_only=0&exclude_drafts=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date', '2026-09-01');
});

test('lessons can be listed for an inclusive date range', function () {
    ['instructor' => $instructor, 'token' => $token] = weekInstructor();

    $later = weekLesson($instructor, '2026-09-22', '14:00');
    $earlier = weekLesson($instructor, '2026-09-21', '09:00');
    weekLesson($instructor, '2026-09-21', '11:00', LessonStatus::CANCELLED);
    weekLesson($instructor, '2026-09-23', '09:00', LessonStatus::DRAFT);
    weekLesson($instructor, '2026-09-20', '09:00');

    $other = Instructor::factory()->create();
    weekLesson($other, '2026-09-21', '09:00');

    $response = $this->withToken($token)->getJson(
        '/api/v1/instructor/lessons?from=2026-09-21&to=2026-09-27'
    );

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$earlier->id, $later->id]);
    expect($response->json('data.0.date'))->toBe('2026-09-21')
        ->and($response->json('data.0.start_time'))->toBe('09:00')
        ->and($response->json('data.1.date'))->toBe('2026-09-22')
        ->and($response->json('data.0.student.first_name'))->not->toBeNull();
});

test('a lesson range matches the single-day payload for each date', function () {
    ['instructor' => $instructor, 'token' => $token] = weekInstructor();
    weekLesson($instructor, '2026-09-21', '09:00');
    weekLesson($instructor, '2026-09-22', '10:00');

    $range = $this->withToken($token)->getJson(
        '/api/v1/instructor/lessons?from=2026-09-21&to=2026-09-22'
    );
    $monday = $this->withToken($token)->getJson('/api/v1/instructor/lessons/2026-09-21');

    $range->assertOk();
    $monday->assertOk();

    expect($range->json('data.0'))->toBe($monday->json('data.0'));
});

test('lesson range validation rejects a partial or oversized span', function (string $query, string $field) {
    ['token' => $token] = weekInstructor();

    $this->withToken($token)
        ->getJson('/api/v1/instructor/lessons?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'neither' => ['', 'from'],
    'only from' => ['from=2026-09-21', 'to'],
    'only to' => ['to=2026-09-27', 'from'],
    'bad format' => ['from=not-a-date&to=2026-09-27', 'from'],
    'to before from' => ['from=2026-09-27&to=2026-09-21', 'to'],
    'longer than 31 days' => ['from=2026-09-01&to=2026-10-02', 'to'],
]);

test('the day lesson route still rejects a missing date segment as not found', function () {
    ['token' => $token] = weekInstructor();

    $this->withToken($token)
        ->getJson('/api/v1/instructor/lessons/not-a-date')
        ->assertUnprocessable();
});
