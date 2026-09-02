<?php

declare(strict_types=1);

use App\Enums\ItsaEnrolmentStatus;
use App\Enums\UserRole;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Models\Instructor;
use App\Models\User;
use App\Services\HmrcItsaService;

function makeItsaInstructorUser(): User
{
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    Instructor::factory()->create([
        'user_id' => $user->id,
        'mtd_itsa_status' => ItsaEnrolmentStatus::Unknown,
        'mtd_itsa_status_checked_at' => null,
    ]);

    return $user;
}

beforeEach(function () {
    createHmrcTestSchema();
});

it('posts refresh-status and flashes success when enrolment is resolved', function () {
    $user = makeItsaInstructorUser();

    $this->mock(HmrcItsaService::class, function ($mock) {
        $mock->shouldReceive('refreshEnrolmentStatus')
            ->once()
            ->andReturn(ItsaEnrolmentStatus::SignedUpVoluntary);
    });

    $this->actingAs($user)
        ->from(route('hmrc.itsa.index'))
        ->post(route('hmrc.itsa.refresh-status'))
        ->assertRedirect(route('hmrc.itsa.index'))
        ->assertSessionHas('success', 'MTD ITSA enrolment status refreshed.');
});

it('flashes the HMRC error instead of claiming a successful refresh', function () {
    $user = makeItsaInstructorUser();

    $this->mock(HmrcItsaService::class, function ($mock) {
        $mock->shouldReceive('refreshEnrolmentStatus')
            ->once()
            ->andThrow(new HmrcApiException('upstream failed', 500, 'INTERNAL_SERVER_ERROR'));
    });

    $this->actingAs($user)
        ->from(route('hmrc.itsa.index'))
        ->post(route('hmrc.itsa.refresh-status'))
        ->assertRedirect(route('hmrc.itsa.index'))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');
});

it('flashes a fingerprint error so the enrolment check is not a silent no-op', function () {
    $user = makeItsaInstructorUser();

    $this->mock(HmrcItsaService::class, function ($mock) {
        $mock->shouldReceive('refreshEnrolmentStatus')
            ->once()
            ->andThrow(new MissingFraudFingerprintException);
    });

    $this->actingAs($user)
        ->from(route('hmrc.itsa.index'))
        ->post(route('hmrc.itsa.refresh-status'))
        ->assertRedirect(route('hmrc.itsa.index'))
        ->assertSessionHas('error', 'A fresh device fingerprint is required before this HMRC action.');
});
