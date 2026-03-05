<?php

use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

test('guests cannot view pupils index', function () {
    $response = $this->get(route('pupils.index'));

    $response->assertRedirect();
});

test('authenticated users can view pupils index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('pupils.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Pupils/Index')
        ->has('pupils')
    );
});

test('pupils index displays students with their names', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Student::factory()->create([
        'first_name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->get(route('pupils.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Pupils/Index')
        ->has('pupils', 1)
        ->where('pupils.0.name', 'Jane Doe')
        ->where('pupils.0.email', 'jane@example.com')
    );
});

test('pupils index displays instructor name when assigned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructorUser = User::factory()->create(['name' => 'John Smith']);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);

    Student::factory()->create([
        'first_name' => 'Alice',
        'surname' => 'Wonder',
        'instructor_id' => $instructor->id,
    ]);

    $response = $this->get(route('pupils.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Pupils/Index')
        ->has('pupils', 1)
        ->where('pupils.0.name', 'Alice Wonder')
        ->where('pupils.0.instructor_id', $instructor->id)
        ->where('pupils.0.instructor_name', 'John Smith')
    );
});

test('pupils index shows null instructor for unassigned students', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Student::factory()->create([
        'first_name' => 'Bob',
        'surname' => 'Builder',
        'instructor_id' => null,
    ]);

    $response = $this->get(route('pupils.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Pupils/Index')
        ->has('pupils', 1)
        ->where('pupils.0.name', 'Bob Builder')
        ->where('pupils.0.instructor_id', null)
        ->where('pupils.0.instructor_name', null)
    );
});

test('pupils index returns students ordered by newest first', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Student::factory()->create([
        'first_name' => 'Older',
        'surname' => 'Student',
        'created_at' => now()->subDays(5),
    ]);
    Student::factory()->create([
        'first_name' => 'Newer',
        'surname' => 'Student',
        'created_at' => now(),
    ]);

    $response = $this->get(route('pupils.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('pupils', 2)
        ->where('pupils.0.name', 'Newer Student')
        ->where('pupils.1.name', 'Older Student')
    );
});
