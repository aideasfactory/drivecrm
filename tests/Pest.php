<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit/Hmrc');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Minimal schema for HMRC archive/ITSA HTTP tests. Full RefreshDatabase
 * migrations are MySQL-specific in this repo (ALTER ... MODIFY ENUM, etc.).
 */
function createHmrcTestSchema(): void
{
    Schema::dropIfExists('year_end_archives');
    Schema::dropIfExists('instructors');
    Schema::dropIfExists('users');
    Schema::dropIfExists('teams');

    Schema::create('teams', function (Blueprint $table) {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('name');
        $table->json('settings')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('role')->default('student');
        $table->unsignedBigInteger('current_team_id')->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique();
        $table->string('stripe_account_id')->nullable();
        $table->boolean('onboarding_complete')->default(false);
        $table->boolean('charges_enabled')->default(false);
        $table->boolean('payouts_enabled')->default(false);
        $table->string('status')->default('active');
        $table->boolean('priority')->default(false);
        $table->string('mtd_itsa_status', 32)->default('unknown');
        $table->timestamp('mtd_itsa_status_checked_at')->nullable();
        $table->timestamps();
    });

    Schema::create('year_end_archives', function (Blueprint $table) {
        $table->id();
        $table->foreignId('instructor_id');
        $table->unsignedSmallInteger('tax_year_start');
        $table->string('status', 16)->default('queued');
        $table->string('file_path')->nullable();
        $table->unsignedBigInteger('file_size_bytes')->nullable();
        $table->json('counts')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamp('queued_at')->nullable();
        $table->timestamp('generated_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('purged_at')->nullable();
        $table->timestamps();
    });
}
