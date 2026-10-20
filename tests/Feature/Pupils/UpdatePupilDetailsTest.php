<?php

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\StudentPickupPoint;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| PUT /students/{student} (web pupil profile "Edit details")
|--------------------------------------------------------------------------
*/

function pupilDetailsPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Janet',
        'surname' => 'Smith',
        'email' => 'janet.smith@example.com',
        'phone' => '07700900099',
        'date_of_birth' => '2007-05-14',
        'contact_first_name' => 'Paula',
        'contact_surname' => 'Smith',
        'contact_email' => 'paula@example.com',
        'contact_phone' => '07700900100',
    ], $overrides);
}

test('an owner can update a pupil booked with an instructor', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $student = Student::factory()->create();

    $response = $this->actingAs($owner)
        ->putJson(route('students.update', $student), pupilDetailsPayload());

    $response->assertSuccessful()
        ->assertJsonPath('student.name', 'Janet Smith')
        ->assertJsonPath('student.date_of_birth', '2007-05-14')
        ->assertJsonPath('student.contact_first_name', 'Paula');

    $student->refresh();

    expect($student->first_name)->toBe('Janet')
        ->and($student->date_of_birth->toDateString())->toBe('2007-05-14')
        ->and($student->user->email)->toBe('janet.smith@example.com')
        ->and($student->user->name)->toBe('Janet Smith');
});

test('the assigned instructor can update their pupil', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $this->actingAs($user)
        ->putJson(route('students.update', $student), pupilDetailsPayload())
        ->assertSuccessful();

    expect($student->fresh()->surname)->toBe('Smith');
});

test('another instructor cannot update the pupil', function () {
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create(['first_name' => 'Original']);

    $this->actingAs($user)
        ->putJson(route('students.update', $student), pupilDetailsPayload())
        ->assertForbidden();

    expect($student->fresh()->first_name)->toBe('Original');
});

test('the date of birth is optional and can be cleared', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $student = Student::factory()->create(['date_of_birth' => '2006-01-01']);

    $this->actingAs($owner)
        ->putJson(route('students.update', $student), pupilDetailsPayload(['date_of_birth' => null]))
        ->assertSuccessful()
        ->assertJsonPath('student.date_of_birth', null);

    expect($student->fresh()->date_of_birth)->toBeNull();
});

test('the date of birth must be in the past', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $student = Student::factory()->create();

    $this->actingAs($owner)
        ->putJson(route('students.update', $student), pupilDetailsPayload([
            'date_of_birth' => now()->addDay()->toDateString(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_of_birth');
});

test('the name is required and status cannot be changed from the details form', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $student = Student::factory()->create(['status' => 'active']);

    $this->actingAs($owner)
        ->putJson(route('students.update', $student), pupilDetailsPayload(['first_name' => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('first_name');

    $this->actingAs($owner)
        ->putJson(route('students.update', $student), pupilDetailsPayload(['status' => 'inactive']))
        ->assertSuccessful();

    expect($student->fresh()->status)->toBe('active');
});

test('the email cannot belong to another user', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    User::factory()->create(['email' => 'taken@example.com']);
    $student = Student::factory()->create();

    $this->actingAs($owner)
        ->putJson(route('students.update', $student), pupilDetailsPayload(['email' => 'taken@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('the pupil detail includes the default pickup point and date of birth', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $student = Student::factory()->create(['date_of_birth' => '2007-05-14']);
    StudentPickupPoint::factory()->create(['student_id' => $student->id]);
    $default = StudentPickupPoint::factory()->create([
        'student_id' => $student->id,
        'address' => '1 High Street, Leeds',
        'postcode' => 'LS1 1AA',
        'is_default' => true,
    ]);

    $this->actingAs($owner)
        ->getJson(route('students.show', $student))
        ->assertSuccessful()
        ->assertJsonPath('student.date_of_birth', '2007-05-14')
        ->assertJsonPath('student.default_pickup_point.id', $default->id)
        ->assertJsonPath('student.default_pickup_point.postcode', 'LS1 1AA');
});

test('a pupil without pickup points has no default pickup point', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $student = Student::factory()->create();

    $this->actingAs($owner)
        ->getJson(route('students.show', $student))
        ->assertSuccessful()
        ->assertJsonPath('student.default_pickup_point', null);
});
