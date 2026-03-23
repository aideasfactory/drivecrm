<?php

use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

test('instructor pupils endpoint returns only active pupils by default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructorUser = User::factory()->create();
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $activeStudent = Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Active',
        'surname' => 'Student',
        'status' => 'active',
    ]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Inactive',
        'surname' => 'Student',
        'status' => 'inactive',
    ]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Passed',
        'surname' => 'Student',
        'status' => 'passed',
    ]);

    $response = $this->getJson(route('instructors.pupils', $instructor));

    $response->assertSuccessful();

    $pupils = $response->json('pupils');
    expect($pupils)->toHaveCount(1);
    expect($pupils[0]['name'])->toBe('Active Student');
});

test('instructor pupils endpoint returns all pupils when status is all', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructorUser = User::factory()->create();
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Active',
        'surname' => 'Student',
        'status' => 'active',
    ]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Inactive',
        'surname' => 'Student',
        'status' => 'inactive',
    ]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Passed',
        'surname' => 'Student',
        'status' => 'passed',
    ]);

    $response = $this->getJson(route('instructors.pupils', ['instructor' => $instructor, 'status' => 'all']));

    $response->assertSuccessful();

    $pupils = $response->json('pupils');
    expect($pupils)->toHaveCount(3);
});

test('instructor pupils endpoint does not return pupils from other instructors', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructorUser = User::factory()->create();
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    $otherInstructor = Instructor::factory()->create();

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'My',
        'surname' => 'Student',
        'status' => 'active',
    ]);

    Student::factory()->create([
        'instructor_id' => $otherInstructor->id,
        'first_name' => 'Other',
        'surname' => 'Student',
        'status' => 'active',
    ]);

    $response = $this->getJson(route('instructors.pupils', $instructor));

    $response->assertSuccessful();

    $pupils = $response->json('pupils');
    expect($pupils)->toHaveCount(1);
    expect($pupils[0]['name'])->toBe('My Student');
});

test('instructor pupils endpoint filters by specific status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructorUser = User::factory()->create();
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Active',
        'surname' => 'Student',
        'status' => 'active',
    ]);

    Student::factory()->create([
        'instructor_id' => $instructor->id,
        'first_name' => 'Inactive',
        'surname' => 'Student',
        'status' => 'inactive',
    ]);

    $response = $this->getJson(route('instructors.pupils', ['instructor' => $instructor, 'status' => 'inactive']));

    $response->assertSuccessful();

    $pupils = $response->json('pupils');
    expect($pupils)->toHaveCount(1);
    expect($pupils[0]['name'])->toBe('Inactive Student');
});
