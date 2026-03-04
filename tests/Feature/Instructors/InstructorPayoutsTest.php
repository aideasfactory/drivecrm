<?php

use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Student;
use App\Models\User;

test('guests cannot view instructor payouts', function () {
    $instructor = Instructor::factory()->create();

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertUnauthorized();
});

test('authenticated users can view instructor payouts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->stripeConnected()->create();

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertOk();
    $response->assertJsonStructure([
        'payouts',
    ]);
});

test('payouts endpoint returns empty array when no payouts exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->stripeConnected()->create();

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertOk();
    $response->assertJson([
        'payouts' => [],
    ]);
});

test('payouts endpoint returns payout data with related information', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->stripeConnected()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'package_name' => '10 Lesson Package',
    ]);
    $lesson = Lesson::factory()->completed()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'amount_pence' => 3500,
        'date' => '2026-03-01',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);
    Payout::factory()->paid()->create([
        'lesson_id' => $lesson->id,
        'instructor_id' => $instructor->id,
        'amount_pence' => 3500,
    ]);

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertOk();
    $response->assertJsonCount(1, 'payouts');
    $response->assertJsonFragment([
        'amount_pence' => 3500,
        'status' => 'paid',
        'student_name' => $student->first_name.' '.$student->surname,
        'package_name' => '10 Lesson Package',
        'lesson_date' => '2026-03-01',
    ]);
});

test('payouts endpoint only returns payouts for the specified instructor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->stripeConnected()->create();
    $otherInstructor = Instructor::factory()->stripeConnected()->create();

    // Create payout for our instructor
    $lesson = Lesson::factory()->completed()->create(['instructor_id' => $instructor->id]);
    Payout::factory()->paid()->create([
        'lesson_id' => $lesson->id,
        'instructor_id' => $instructor->id,
    ]);

    // Create payout for other instructor
    $otherLesson = Lesson::factory()->completed()->create(['instructor_id' => $otherInstructor->id]);
    Payout::factory()->paid()->create([
        'lesson_id' => $otherLesson->id,
        'instructor_id' => $otherInstructor->id,
    ]);

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertOk();
    $response->assertJsonCount(1, 'payouts');
});

test('payouts endpoint returns payouts with all status types', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->stripeConnected()->create();

    // Create paid payout
    $lesson1 = Lesson::factory()->completed()->create(['instructor_id' => $instructor->id]);
    Payout::factory()->paid()->create([
        'lesson_id' => $lesson1->id,
        'instructor_id' => $instructor->id,
    ]);

    // Create pending payout
    $lesson2 = Lesson::factory()->completed()->create(['instructor_id' => $instructor->id]);
    Payout::factory()->create([
        'lesson_id' => $lesson2->id,
        'instructor_id' => $instructor->id,
    ]);

    // Create failed payout
    $lesson3 = Lesson::factory()->completed()->create(['instructor_id' => $instructor->id]);
    Payout::factory()->failed()->create([
        'lesson_id' => $lesson3->id,
        'instructor_id' => $instructor->id,
    ]);

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertOk();
    $response->assertJsonCount(3, 'payouts');

    $statuses = collect($response->json('payouts'))->pluck('status')->toArray();
    expect($statuses)->toContain('paid');
    expect($statuses)->toContain('pending');
    expect($statuses)->toContain('failed');
});

test('payouts response includes formatted amount', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructor = Instructor::factory()->stripeConnected()->create();
    $lesson = Lesson::factory()->completed()->create(['instructor_id' => $instructor->id]);
    Payout::factory()->paid()->create([
        'lesson_id' => $lesson->id,
        'instructor_id' => $instructor->id,
        'amount_pence' => 5000,
    ]);

    $response = $this->getJson(route('instructors.payouts', $instructor));

    $response->assertOk();
    $response->assertJsonFragment([
        'formatted_amount' => '£50.00',
    ]);
});
