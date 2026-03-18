<?php

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
| POST /api/v1/students/{student}/lessons/{lesson}/sign-off
|--------------------------------------------------------------------------
*/

test('an instructor can sign off a lesson for their student', function () {
    Queue::fake();

    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->stripeConnected()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => now()->subDay()->format('Y-m-d'),
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => 'Great lesson covering parallel parking and hill starts.',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Lesson sign-off is being processed.');

    Queue::assertPushed(ProcessLessonSignOffJob::class, function ($job) use ($lesson, $instructor) {
        return $job->lesson->id === $lesson->id
            && $job->instructor->id === $instructor->id
            && $job->summary === 'Great lesson covering parallel parking and hill starts.';
    });
});

test('sign-off requires authentication', function () {
    $student = Student::factory()->create();
    $lesson = Lesson::factory()->create();

    $response = $this->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => 'A summary.',
    ]);

    $response->assertUnauthorized();
});

test('a student cannot sign off a lesson', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create(['student_id' => $student->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => 'A summary.',
    ]);

    $response->assertForbidden();
});

test('an instructor cannot sign off a lesson for a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->stripeConnected()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id]);
    $order = Order::factory()->create(['student_id' => $student->id, 'instructor_id' => $otherInstructor->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id, 'instructor_id' => $otherInstructor->id]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => 'A summary.',
    ]);

    $response->assertForbidden();
});

test('sign-off fails if lesson does not belong to student', function () {
    Queue::fake();

    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->stripeConnected()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    // Lesson belongs to a different student's order
    $otherStudent = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create(['student_id' => $otherStudent->id, 'instructor_id' => $instructor->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id, 'instructor_id' => $instructor->id]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => 'A summary.',
    ]);

    $response->assertNotFound()
        ->assertJsonPath('message', 'Lesson not found for this student.');

    Queue::assertNothingPushed();
});

test('sign-off fails if lesson is already completed', function () {
    Queue::fake();

    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->stripeConnected()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $lesson = Lesson::factory()->completed()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => 'A summary.',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'This lesson has already been completed.');

    Queue::assertNothingPushed();
});

test('sign-off requires a summary', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->stripeConnected()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id, 'instructor_id' => $instructor->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['summary']);
});

test('sign-off rejects summary over 5000 characters', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->stripeConnected()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $lesson = Lesson::factory()->create(['order_id' => $order->id, 'instructor_id' => $instructor->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/lessons/{$lesson->id}/sign-off", [
        'summary' => str_repeat('a', 5001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['summary']);
});
