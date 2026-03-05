<?php

use App\Models\Instructor;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

test('guests cannot view packages index', function () {
    $response = $this->get(route('packages.index'));

    $response->assertRedirect();
});

test('authenticated users can view packages index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('packages.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Packages/Index')
        ->has('packages')
    );
});

test('packages index displays admin packages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Package::factory()->create(['name' => 'Admin Gold Package']);

    $response = $this->get(route('packages.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Packages/Index')
        ->has('packages', 1)
        ->where('packages.0.name', 'Admin Gold Package')
        ->where('packages.0.is_platform_package', true)
        ->where('packages.0.instructor_name', null)
    );
});

test('packages index displays instructor packages with instructor name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $instructorUser = User::factory()->create(['name' => 'John Smith']);
    $instructor = Instructor::factory()->create(['user_id' => $instructorUser->id]);
    Package::factory()->forInstructor($instructor)->create(['name' => 'Instructor Package']);

    $response = $this->get(route('packages.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Packages/Index')
        ->has('packages', 1)
        ->where('packages.0.name', 'Instructor Package')
        ->where('packages.0.is_platform_package', false)
        ->where('packages.0.instructor_name', 'John Smith')
    );
});

test('guests cannot store packages', function () {
    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->postJson(route('packages.store'), [
            'name' => 'Test Package',
            'total_price_pence' => 50000,
            'lessons_count' => 10,
        ]);

    $response->assertUnauthorized();
});

test('authenticated users can create an admin package', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('packages.store'), [
            'name' => 'New Admin Package',
            'description' => 'A global admin package',
            'total_price_pence' => 60000,
            'lessons_count' => 12,
        ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'name' => 'New Admin Package',
        'total_price_pence' => 60000,
        'lessons_count' => 12,
        'is_platform_package' => true,
    ]);

    $this->assertDatabaseHas('packages', [
        'name' => 'New Admin Package',
        'instructor_id' => null,
        'total_price_pence' => 60000,
        'lessons_count' => 12,
    ]);
});

test('admin package is created without instructor_id', function () {
    $user = User::factory()->create();

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('packages.store'), [
            'name' => 'Global Package',
            'total_price_pence' => 40000,
            'lessons_count' => 8,
        ]);

    $package = Package::where('name', 'Global Package')->first();

    expect($package)->not->toBeNull();
    expect($package->instructor_id)->toBeNull();
    expect($package->isPlatformPackage())->toBeTrue();
});

test('store package validates required fields', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('packages.store'), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'total_price_pence', 'lessons_count']);
});

test('packages index returns packages ordered by newest first', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Package::factory()->create([
        'name' => 'Older Package',
        'created_at' => now()->subDays(5),
    ]);
    Package::factory()->create([
        'name' => 'Newer Package',
        'created_at' => now(),
    ]);

    $response = $this->get(route('packages.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('packages', 2)
        ->where('packages.0.name', 'Newer Package')
        ->where('packages.1.name', 'Older Package')
    );
});
