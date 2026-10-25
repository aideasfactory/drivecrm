<?php

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Jobs\ProcessLessonSignOffJob;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| POST /students/{student}/lessons/{lesson}/sign-off (admin Lessons tab)
|--------------------------------------------------------------------------
|
| A paid lesson can't be signed off until its start time (UK) has passed.
|
*/

function paidInFullLesson(string $date, string $startTime): array
{
    $instructor = Instructor::factory()->create([
        'onboarding_complete' => true,
        'payouts_enabled' => true,
    ]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::ACTIVE,
    ]);
    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'status' => LessonStatus::PENDING,
        'date' => $date,
        'start_time' => $startTime,
        'end_time' => '23:00',
    ]);

    return [$student, $lesson];
}

beforeEach(function () {
    Queue::fake();
    // 12:30 UTC is 13:30 in London (British Summer Time).
    $this->travelTo(now()->setDate(2026, 7, 15)->setTime(12, 30));
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
});

test('a paid lesson on a later day cannot be signed off', function () {
    [$student, $lesson] = paidInFullLesson('2026-07-22', '10:00');

    $this->actingAs($this->owner)
        ->postJson(route('students.lessons.sign-off', [$student, $lesson]), ['summary' => 'Went well'])
        ->assertUnprocessable()
        ->assertJsonPath('message', "This lesson can't be signed off until it has started (22 Jul 2026 at 10:00).");

    expect($lesson->fresh()->status)->toBe(LessonStatus::PENDING);
    Queue::assertNotPushed(ProcessLessonSignOffJob::class);
});

test('a paid lesson later today cannot be signed off', function () {
    [$student, $lesson] = paidInFullLesson('2026-07-15', '14:00');

    $this->actingAs($this->owner)
        ->postJson(route('students.lessons.sign-off', [$student, $lesson]), ['summary' => 'Went well'])
        ->assertUnprocessable();

    Queue::assertNotPushed(ProcessLessonSignOffJob::class);
});

test('a paid lesson that started earlier today in UK time can be signed off', function () {
    [$student, $lesson] = paidInFullLesson('2026-07-15', '13:15');

    $this->actingAs($this->owner)
        ->postJson(route('students.lessons.sign-off', [$student, $lesson]), ['summary' => 'Went well'])
        ->assertSuccessful();

    Queue::assertPushed(ProcessLessonSignOffJob::class);
});

test('a paid lesson on an earlier day can be signed off', function () {
    [$student, $lesson] = paidInFullLesson('2026-07-08', '10:00');

    $this->actingAs($this->owner)
        ->postJson(route('students.lessons.sign-off', [$student, $lesson]), ['summary' => 'Went well'])
        ->assertSuccessful();

    Queue::assertPushed(ProcessLessonSignOffJob::class);
});

test('the lessons list tells the admin tab whether each lesson has started', function () {
    [$student, $futureLesson] = paidInFullLesson('2026-07-22', '10:00');
    $pastLesson = Lesson::factory()->create([
        'order_id' => $futureLesson->order_id,
        'instructor_id' => $futureLesson->instructor_id,
        'date' => '2026-07-08',
        'start_time' => '10:00',
    ]);

    $lessons = collect($this->actingAs($this->owner)
        ->getJson(route('students.lessons', $student))
        ->assertSuccessful()
        ->json('lessons'))
        ->keyBy('id');

    expect($lessons[$futureLesson->id]['has_started'])->toBeFalse()
        ->and($lessons[$pastLesson->id]['has_started'])->toBeTrue();
});

test('the lessons list is sorted oldest first', function () {
    [$student, $futureLesson] = paidInFullLesson('2026-07-22', '10:00');
    $pastLesson = Lesson::factory()->create([
        'order_id' => $futureLesson->order_id,
        'instructor_id' => $futureLesson->instructor_id,
        'date' => '2026-07-08',
        'start_time' => '10:00',
    ]);

    $lessonIds = $this->actingAs($this->owner)
        ->getJson(route('students.lessons', $student))
        ->assertSuccessful()
        ->json('lessons.*.id');

    expect($lessonIds)->toBe([$pastLesson->id, $futureLesson->id]);
});
