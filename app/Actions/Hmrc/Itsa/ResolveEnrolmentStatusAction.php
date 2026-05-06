<?php

declare(strict_types=1);

namespace App\Actions\Hmrc\Itsa;

use App\Enums\HmrcErrorCode;
use App\Enums\ItsaEnrolmentStatus;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ResolveEnrolmentStatusAction
{
    public function __construct(
        private readonly ListBusinessesAction $listBusinesses,
    ) {}

    /**
     * Translate the response of Business Details into an `ItsaEnrolmentStatus`
     * and persist it on the instructor. Idempotent.
     *
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    public function __invoke(User $user, array $fraudContext = []): ItsaEnrolmentStatus
    {
        $instructor = $user->instructor;
        if ($instructor === null) {
            return ItsaEnrolmentStatus::NotSignedUp;
        }

        $status = $this->resolve($user, $instructor, $fraudContext);

        $instructor->forceFill([
            'mtd_itsa_status' => $status,
            'mtd_itsa_status_checked_at' => now(),
        ])->save();

        return $status;
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $fraudContext
     */
    private function resolve(User $user, Instructor $instructor, array $fraudContext): ItsaEnrolmentStatus
    {
        try {
            $businesses = ($this->listBusinesses)($user, $fraudContext);
        } catch (HmrcApiException $exception) {
            $code = $exception->errorCode();

            if ($code === HmrcErrorCode::RuleNotSignedUpToMtd) {
                return ItsaEnrolmentStatus::NotSignedUp;
            }

            // Genuine API problem (auth/network/etc.) — leave the status alone
            // and surface the error so the caller can decide what to do.
            Log::warning('ITSA enrolment resolution failed', [
                'user_id' => $user->id,
                'status' => $exception->statusCode,
                'hmrc_code' => $exception->hmrcCode,
            ]);
            throw $exception;
        }

        if ($businesses->isEmpty()) {
            return ItsaEnrolmentStatus::IncomeSourceMissing;
        }

        return $this->isMandated($instructor)
            ? ItsaEnrolmentStatus::Mandated
            : ItsaEnrolmentStatus::SignedUpVoluntary;
    }

    /**
     * Coarse check: are we past the instructor's likely mandate band given the
     * threshold dates from Phase 1.5? In v1 we don't know the instructor's
     * qualifying income, so we treat anyone past 6 Apr 2026 as "mandated"
     * conservatively. UI copy clarifies the distinction.
     */
    private function isMandated(Instructor $instructor): bool
    {
        return now()->greaterThanOrEqualTo('2026-04-06');
    }
}
