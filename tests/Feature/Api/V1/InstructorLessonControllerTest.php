<?php

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
| GET /api/v1/instructor/lessons/{date}
|--------------------------------------------------------------------------
*/

test('an instructor can retrieve their day lessons', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    $today = now()->format('Y-m-d');

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
        'start_time' => '11:00',
        'end_time' => '12:00',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('day lessons are ordered by start_time', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    $today = now()->format('Y-m-d');

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
        'start_time' => '14:00',
        'end_time' => '15:00',
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk();
    $data = $response->json('data');
    expect($data[0]['start_time'])->toBe('09:00');
    expect($data[1]['start_time'])->toBe('14:00');
});

test('day lessons include student data', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $student = Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Jane',
        'surname' => 'Doe',
    ]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    $today = now()->format('Y-m-d');

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonPath('data.0.student.first_name', 'Jane')
        ->assertJsonPath('data.0.student.surname', 'Doe');
});

test('day lessons include expected resource structure', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    $today = now()->format('Y-m-d');

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'order_id',
                    'date',
                    'start_time',
                    'end_time',
                    'status',
                    'completed_at',
                    'summary',
                    'amount_pence',
                    'student' => [
                        'id',
                        'first_name',
                        'surname',
                        'email',
                        'phone',
                        'status',
                    ],
                    'package_name',
                    'payment_status',
                    'payment_mode',
                    'payout_status',
                    'has_payout',
                    'calendar_item',
                    'has_reflective_log',
                    'resources_count',
                ],
            ],
        ]);
});

test('lessons are scoped to the authenticated instructor only', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $today = now()->format('Y-m-d');

    // Lesson for this instructor
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);
    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
    ]);

    // Lesson for a different instructor
    $otherInstructor = Instructor::factory()->create();
    $otherStudent = Student::factory()->create(['instructor_id' => $otherInstructor->id]);
    $otherOrder = Order::factory()->create([
        'student_id' => $otherStudent->id,
        'instructor_id' => $otherInstructor->id,
    ]);
    Lesson::factory()->create([
        'order_id' => $otherOrder->id,
        'instructor_id' => $otherInstructor->id,
        'date' => $today,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('lessons on a different date are not returned', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    $today = now()->format('Y-m-d');
    $tomorrow = now()->addDay()->format('Y-m-d');

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $tomorrow,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('empty day returns empty data array', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $today = now()->format('Y-m-d');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('invalid date returns validation error', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/lessons/not-a-date');

    $response->assertUnprocessable();
});

test('get day lessons requires authentication', function () {
    $today = now()->format('Y-m-d');

    $response = $this->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertUnauthorized();
});

test('day lessons include calendar item data when present', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $today = now()->format('Y-m-d');

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => $today,
    ]);

    $calendarItem = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
    ]);

    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'date' => $today,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'calendar_item_id' => $calendarItem->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/instructor/lessons/{$today}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'calendar_item' => [
                        'id',
                        'start_time',
                        'end_time',
                        'status',
                        'item_type',
                        'notes',
                    ],
                ],
            ],
        ]);
});
