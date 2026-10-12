<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Models\HmrcDeviceIdentifier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class EnsureHmrcDeviceIdentifierAction
{
    /**
     * Persist the originating device's Gov-Client-Device-ID and mirror it
     * to the long-lived hmrc_device_id cookie.
     */
    public function __invoke(Request $request, User $user): HmrcDeviceIdentifier
    {
        $cookieName = (string) config('hmrc.device_cookie.name', 'hmrc_device_id');
        $cookieValue = $request->cookie($cookieName);

        $identifier = HmrcDeviceIdentifier::forUser($user, is_string($cookieValue) ? $cookieValue : null);

        if ($cookieValue !== $identifier->device_id) {
            Cookie::queue(Cookie::make(
                $cookieName,
                $identifier->device_id,
                (int) config('hmrc.device_cookie.lifetime_minutes', 60 * 24 * 365 * 10),
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
        }

        return $identifier;
    }
}
