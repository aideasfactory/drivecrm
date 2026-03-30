<?php

use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can reset an instructor password', function () {
    $admin = User::factory()->create();
    $instructor = Instructor::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->putJson(route('instructors.password.update', $instructor), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Password has been reset successfully.']);

    expect(Hash::check('new-secure-password', $instructor->user->refresh()->password))->toBeTrue();
});

test('admin can reset a student password', function () {
    $admin = User::factory()->create();
    $student = Student::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->putJson(route('students.password.update', $student), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Password has been reset successfully.']);

    expect(Hash::check('new-secure-password', $student->user->refresh()->password))->toBeTrue();
});

test('password confirmation must match when resetting instructor password', function () {
    $admin = User::factory()->create();
    $instructor = Instructor::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->putJson(route('instructors.password.update', $instructor), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password');
});

test('password confirmation must match when resetting student password', function () {
    $admin = User::factory()->create();
    $student = Student::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->putJson(route('students.password.update', $student), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password');
});

test('password is required when resetting instructor password', function () {
    $admin = User::factory()->create();
    $instructor = Instructor::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->putJson(route('instructors.password.update', $instructor), [
            'password' => '',
            'password_confirmation' => '',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password');
});

test('student without user account returns error on password reset', function () {
    $admin = User::factory()->create();
    $student = Student::factory()->create(['user_id' => null]);

    $response = $this
        ->actingAs($admin)
        ->putJson(route('students.password.update', $student), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Student does not have a user account.']);
});

test('guests cannot reset instructor password', function () {
    $instructor = Instructor::factory()->create();

    $response = $this
        ->putJson(route('instructors.password.update', $instructor), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

    $response->assertUnauthorized();
});

test('guests cannot reset student password', function () {
    $student = Student::factory()->create();

    $response = $this
        ->putJson(route('students.password.update', $student), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

    $response->assertUnauthorized();
});
