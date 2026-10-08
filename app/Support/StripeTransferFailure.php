<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides which Stripe Transfer failures are a platform-balance problem.
 *
 * Those must not block lesson sign-off. Anything else (card declines, a
 * connected account that cannot receive transfers, bad amounts) still should.
 */
final class StripeTransferFailure
{
    public static function isPlatformBalanceShortfall(?string $code, ?string $message): bool
    {
        if ($code === 'balance_insufficient') {
            return true;
        }

        $normalized = strtolower($message ?? '');

        if ($normalized === '' || str_contains($normalized, 'card')) {
            return false;
        }

        return str_contains($normalized, 'insufficient')
            && str_contains($normalized, 'stripe account');
    }

    /**
     * Instructors must not see a platform-balance failure. Staff still do.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function concealFromInstructor(array $payload, string $statusKey = 'status', string $messageKey = 'failure_message'): array
    {
        if (($payload[$statusKey] ?? null) !== 'failed') {
            return $payload;
        }

        $code = isset($payload['failure_code']) && is_string($payload['failure_code'])
            ? $payload['failure_code']
            : null;
        $message = isset($payload[$messageKey]) && is_string($payload[$messageKey])
            ? $payload[$messageKey]
            : null;

        if (! self::isPlatformBalanceShortfall($code, $message)) {
            return $payload;
        }

        $payload[$statusKey] = 'pending';
        $payload[$messageKey] = null;

        if (array_key_exists('failure_code', $payload)) {
            $payload['failure_code'] = null;
        }

        return $payload;
    }
}
