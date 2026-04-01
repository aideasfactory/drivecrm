<?php

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Resource;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/student/home-feed
|--------------------------------------------------------------------------
*/

test('a student can view their home feed', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $instructor = Instructor::factory()->create(['bio' => 'Experienced instructor']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'status' => 'active',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    // Past completed lesson
    Lesson::factory()->completed()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => now()->subDays(3),
    ]);

    // Upcoming lesson (next)
    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => now()->addDays(1),
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    // Upcoming lesson (following)
    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => now()->addDays(3),
        'start_time' => '14:00',
        'end_time' => '15:00',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/student/home-feed');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'has_instructor',
                'next_lesson',
                'following_lesson',
                'special_offer',
                'purchased_hours',
                'learning_resources',
                'instructor',
            ],
        ])
        ->assertJsonPath('data.has_instructor', true)
        ->assertJsonPath('data.purchased_hours', 3)
        ->assertJsonPath('data.instructor.bio', 'Experienced instructor');

    expect($response->json('data.next_lesson'))->not->toBeNull();
    expect($response->json('data.following_lesson'))->not->toBeNull();
});

test('a student without an instructor sees null instructor and offer', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'instructor_id' => null,
        'status' => 'active',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/student/home-feed');

    $response->assertOk()
        ->assertJsonPath('data.has_instructor', false)
        ->assertJsonPath('data.instructor', null)
        ->assertJsonPath('data.special_offer', null)
        ->assertJsonPath('data.purchased_hours', 0)
        ->assertJsonPath('data.next_lesson', null)
        ->assertJsonPath('data.following_lesson', null);
});

test('a student with no upcoming lessons sees null for lesson fields', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'status' => 'active',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    // Only past completed lessons
    Lesson::factory()->completed()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => now()->subDays(5),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/student/home-feed');

    $response->assertOk()
        ->assertJsonPath('data.next_lesson', null)
        ->assertJsonPath('data.following_lesson', null)
        ->assertJsonPath('data.purchased_hours', 1);
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/v1/student/home-feed')
        ->assertUnauthorized();
});

test('special offer is returned from instructor meta', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $instructor = Instructor::factory()->create([
        'meta' => ['special_offer' => '20% off your first 10 lessons!'],
    ]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'status' => 'active',
    ]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/student/home-feed');

    $response->assertOk()
        ->assertJsonPath('data.special_offer', '20% off your first 10 lessons!');
});
