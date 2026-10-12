<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(
        private readonly LoginResponseContract $loginResponse,
    ) {}

    /**
     * Record the factor that just succeeded so later HMRC calls can send
     * Gov-Client-Multi-Factor for this session.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $type = $request->filled('recovery_code') ? 'OTHER' : 'TOTP';
            $productKey = (string) config('hmrc.fraud_headers.user_id_key', 'drivecrm');

            $request->session()->put('hmrc.mfa', [
                'type' => $type,
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'unique_reference' => hash('sha256', $productKey.':'.$type.':'.$user->id),
            ]);
        }

        return $this->loginResponse->toResponse($request);
    }
}
