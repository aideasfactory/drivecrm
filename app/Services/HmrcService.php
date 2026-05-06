<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Hmrc\BuildAuthorizationUrlAction;
use App\Actions\Hmrc\CallHmrcApiAction;
use App\Actions\Hmrc\ExchangeAuthorizationCodeAction;
use App\Actions\Hmrc\GetValidAccessTokenAction;
use App\Actions\Hmrc\HelloWorldAction;
use App\Actions\Hmrc\Profile\GetMtdApplicabilityAction;
use App\Actions\Hmrc\Profile\UpdateTaxProfileAction;
use App\Actions\Hmrc\RefreshAccessTokenAction;
use App\Actions\Hmrc\ValidateFraudHeadersAction;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Models\HmrcClientFingerprint;
use App\Models\HmrcToken;
use App\Models\Instructor;
use App\Models\User;

class HmrcService extends BaseService
{
    public function __construct(
        protected BuildAuthorizationUrlAction $buildAuthorizationUrl,
        protected ExchangeAuthorizationCodeAction $exchangeAuthorizationCode,
        protected RefreshAccessTokenAction $refreshAccessToken,
        protected GetValidAccessTokenAction $getValidAccessToken,
        protected CallHmrcApiAction $callHmrcApi,
        protected HelloWorldAction $helloWorld,
        protected UpdateTaxProfileAction $updateTaxProfile,
        protected GetMtdApplicabilityAction $getMtdApplicability,
        protected ValidateFraudHeadersAction $validateFraudHeaders,
    ) {}

    /**
     * @return array{
     *     connected: bool,
     *     connected_at: ?string,
     *     expires_at: ?string,
     *     refresh_expires_at: ?string,
     *     scopes: array<int, string>,
     *     days_until_refresh_expiry: ?int,
     * }
     */
    public function connectionStatusFor(User $user): array
    {
        $token = HmrcToken::query()->where('user_id', $user->id)->first();

        if (! $token) {
            return [
                'connected' => false,
                'connected_at' => null,
                'expires_at' => null,
                'refresh_expires_at' => null,
                'scopes' => [],
                'days_until_refresh_expiry' => null,
            ];
        }

        return [
            'connected' => true,
            'connected_at' => $token->connected_at->toIso8601String(),
            'expires_at' => $token->expires_at->toIso8601String(),
            'refresh_expires_at' => $token->refresh_expires_at->toIso8601String(),
            'scopes' => $token->scopes ?? [],
            'days_until_refresh_expiry' => $token->daysUntilRefreshExpiry(),
        ];
    }

    public function beginAuthorization(User $user): string
    {
        return ($this->buildAuthorizationUrl)($user, $this->scopesFor($user));
    }

    /**
     * Resolve the union of scopes DRIVE should request for this user.
     *
     * Always includes the `hello` scope (Phase 1 diagnostics). Adds ITSA scopes
     * if the instructor's tax profile makes them applicable. Adds VAT scopes
     * for VAT-registered instructors (Phase 4 turns this on; the policy lives
     * here so future scopes are additive, never narrowed).
     *
     * Existing granted scopes are preserved — re-auth never narrows.
     *
     * @return array<int, string>
     */
    public function scopesFor(User $user): array
    {
        $scopes = (array) config('hmrc.scopes.hello_world', ['hello']);

        $instructor = $user->instructor;
        if ($instructor !== null) {
            $applicability = ($this->getMtdApplicability)($instructor);
            if (($applicability['itsa']['applies'] ?? false) === true) {
                $scopes = array_merge($scopes, (array) config('hmrc.scopes.itsa', []));
            }
            if (($applicability['vat']['applies'] ?? false) === true) {
                $scopes = array_merge($scopes, (array) config('hmrc.scopes.vat', []));
            }
        }

        $existingToken = HmrcToken::query()->where('user_id', $user->id)->first();
        if ($existingToken !== null && is_array($existingToken->scopes)) {
            $scopes = array_merge($scopes, $existingToken->scopes);
        }

        return array_values(array_unique($scopes));
    }

    public function completeAuthorization(User $user, string $code, string $state): HmrcToken
    {
        return ($this->exchangeAuthorizationCode)($user, $code, $state);
    }

    public function disconnect(User $user): void
    {
        HmrcToken::query()->where('user_id', $user->id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function helloWorld(User $user): array
    {
        return ($this->helloWorld)($user);
    }

    /**
     * @return array{
     *     completed_at: ?string,
     *     business_type: ?string,
     *     vat_registered: bool,
     *     vrn: ?string,
     *     utr: ?string,
     *     nino: ?string,
     *     companies_house_number: ?string,
     * }
     */
    public function getTaxProfile(Instructor $instructor): array
    {
        return [
            'completed_at' => $instructor->tax_profile_completed_at?->toIso8601String(),
            'business_type' => $instructor->business_type?->value,
            'vat_registered' => (bool) $instructor->vat_registered,
            'vrn' => $instructor->vrn,
            'utr' => $instructor->utr,
            'nino' => $instructor->nino,
            'companies_house_number' => $instructor->companies_house_number,
        ];
    }

    /**
     * @param  array{
     *     business_type: string,
     *     vat_registered: bool,
     *     vrn?: ?string,
     *     utr?: ?string,
     *     nino?: ?string,
     *     companies_house_number?: ?string,
     * }  $data
     */
    public function updateTaxProfile(Instructor $instructor, array $data): Instructor
    {
        return ($this->updateTaxProfile)($instructor, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMtdApplicability(Instructor $instructor): array
    {
        return ($this->getMtdApplicability)($instructor);
    }

    /**
     * Persist (upsert) the user's current device fingerprint against their active token.
     *
     * @param  array{
     *     screens: array<int, array<string, mixed>>,
     *     window_size: array<string, mixed>,
     *     timezone: array<string, mixed>,
     *     browser_user_agent: string,
     * }  $data
     */
    public function storeFingerprint(User $user, array $data): HmrcClientFingerprint
    {
        $token = HmrcToken::query()->where('user_id', $user->id)->first();
        if ($token === null) {
            throw new MissingFraudFingerprintException(
                'You must connect to HMRC before recording a device fingerprint.',
            );
        }

        return HmrcClientFingerprint::updateOrCreate(
            ['hmrc_token_id' => $token->id],
            [
                'screens' => $data['screens'],
                'window_size' => $data['window_size'],
                'timezone' => $data['timezone'],
                'browser_user_agent' => $data['browser_user_agent'],
                'captured_at' => now(),
            ],
        );
    }

    /**
     * Whether a usable (non-stale) fingerprint exists for the user's current token.
     */
    public function hasFreshFingerprint(User $user): bool
    {
        $token = HmrcToken::query()->where('user_id', $user->id)->first();
        if ($token === null) {
            return false;
        }

        $fingerprint = HmrcClientFingerprint::query()
            ->where('hmrc_token_id', $token->id)
            ->first();

        if ($fingerprint === null) {
            return false;
        }

        $maxAge = (int) config('hmrc.fraud_headers.fingerprint_max_age_minutes', 30);

        return ! $fingerprint->isStale($maxAge);
    }

    /**
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool}  $context
     * @return array<string, mixed>
     */
    public function validateFraudHeaders(User $user, array $context = []): array
    {
        return ($this->validateFraudHeaders)($user, $context);
    }
}
