<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/v1/students/{student}/orders
|--------------------------------------------------------------------------
*/

test('an instructor can list orders for their student', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'amount_pence' => $lesson->amount_pence,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'student_id',
                    'instructor_id',
                    'package_name',
                    'payment_mode',
                    'status',
                    'total_price_pence',
                    'lessons' => [
                        '*' => [
                            'id',
                            'order_id',
                            'status',
                            'date',
                            'start_time',
                            'end_time',
                            'lesson_payment' => [
                                'id',
                                'amount_pence',
                                'status',
                                'due_date',
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.payment_mode', 'weekly');
});

test('a student can list their own orders', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    Order::factory()->create([
        'student_id' => $student->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('an instructor cannot list orders for a student not assigned to them', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test Device')->plainTextToken;

    $otherInstructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $otherInstructor->id, 'status' => 'active']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders");

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/students/{student}/orders/{order}
|--------------------------------------------------------------------------
*/

test('an instructor can view a specific order with lessons and payments', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
        'package_lessons_count' => 3,
    ]);

    for ($i = 0; $i < 3; $i++) {
        $lesson = Lesson::factory()->create([
            'order_id' => $order->id,
            'instructor_id' => $instructor->id,
            'date' => now()->addWeeks($i)->toDateString(),
        ]);

        LessonPayment::factory()->create([
            'lesson_id' => $lesson->id,
            'amount_pence' => $lesson->amount_pence,
            'due_date' => now()->addWeeks($i)->subHours(24),
        ]);
    }

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'student_id',
                'package_name',
                'payment_mode',
                'status',
                'total_price_pence',
                'lessons' => [
                    '*' => [
                        'id',
                        'date',
                        'start_time',
                        'end_time',
                        'status',
                        'lesson_payment' => [
                            'id',
                            'amount_pence',
                            'status',
                            'due_date',
                        ],
                    ],
                ],
            ],
        ])
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonCount(3, 'data.lessons');
});

test('viewing an order belonging to a different student returns 404', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $otherStudent = Student::factory()->create(['instructor_id' => $instructor->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $otherStudent->id,
        'instructor_id' => $instructor->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders/{$order->id}");

    $response->assertNotFound();
});

test('weekly order includes lesson payments with due status', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
    ]);

    LessonPayment::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => PaymentStatus::DUE,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.payment_mode', 'weekly')
        ->assertJsonPath('data.lessons.0.lesson_payment.status', 'due');
});

test('upfront order lessons have no lesson payments', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    $token = $user->createToken('Test Device')->plainTextToken;

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::ACTIVE,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/students/{$student->id}/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.payment_mode', 'upfront')
        ->assertJsonMissing(['lesson_payment' => ['id']]);
});

test('unauthenticated user cannot access orders', function () {
    $student = Student::factory()->create(['status' => 'active']);

    $this->getJson("/api/v1/students/{$student->id}/orders")
        ->assertUnauthorized();

    $order = Order::factory()->create(['student_id' => $student->id]);

    $this->getJson("/api/v1/students/{$student->id}/orders/{$order->id}")
        ->assertUnauthorized();
});
