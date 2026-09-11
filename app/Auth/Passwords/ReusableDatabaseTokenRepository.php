<?php

declare(strict_types=1);

namespace App\Auth\Passwords;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Laravel password-reset tokens are hashed, and the default repository
 * deletes the outstanding row before inserting a new hash. That makes
 * every earlier unused email link fail with an invalid-token error.
 *
 * This repository keeps a single row per email (Laravel's table shape)
 * and resends the same plaintext token until it expires or the password
 * is changed. The bcrypt `token` column is unchanged so `exists()` stays
 * on Laravel's hasher. An APP_KEY-encrypted copy is stored so the value
 * can be returned on later create() calls.
 *
 * Trade-offs vs Laravel defaults:
 * - expire=0 is treated as "never expires while unused". Laravel's own
 *   tokenExpired(0) would mark tokens expired immediately. Immortal
 *   unused links are a mailbox-compromise risk, so config defaults to
 *   24 hours. Throttle is unchanged.
 * - The encrypted copy is recoverable if APP_KEY leaks with the database.
 *   Verification still uses the bcrypt hash.
 */
class ReusableDatabaseTokenRepository extends DatabaseTokenRepository
{
    /**
     * Create a new token, or return the outstanding unused one.
     */
    public function create(CanResetPasswordContract $user): string
    {
        $email = $user->getEmailForPasswordReset();
        $existing = (array) $this->getTable()->where('email', $email)->first();

        if ($this->canReuseExistingToken($existing)) {
            $plain = $this->decryptStoredToken($existing['encrypted_token'] ?? null);

            if (is_string($plain) && $plain !== '') {
                $this->getTable()->where('email', $email)->update([
                    'created_at' => new Carbon,
                ]);

                return $plain;
            }
        }

        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($email, $token));

        return $token;
    }

    /**
     * Build the record payload for the table.
     *
     * @return array{email: string, token: string, encrypted_token: string, created_at: Carbon}
     */
    protected function getPayload($email, #[\SensitiveParameter] $token): array
    {
        return [
            'email' => $email,
            'token' => $this->hasher->make($token),
            'encrypted_token' => Crypt::encryptString($token),
            'created_at' => new Carbon,
        ];
    }

    /**
     * Determine if the token has expired.
     *
     * An expire value of 0 (or less) means the outstanding token stays
     * valid until it is deleted — typically when the password is reset.
     */
    protected function tokenExpired($createdAt): bool
    {
        if ($this->expires <= 0) {
            return false;
        }

        return parent::tokenExpired($createdAt);
    }

    /**
     * Delete expired tokens. A never-expire repository has none.
     */
    public function deleteExpired(): void
    {
        if ($this->expires <= 0) {
            return;
        }

        parent::deleteExpired();
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    protected function canReuseExistingToken(array $existing): bool
    {
        if ($existing === [] || ! isset($existing['created_at'])) {
            return false;
        }

        if ($this->tokenExpired($existing['created_at'])) {
            return false;
        }

        $plain = $this->decryptStoredToken($existing['encrypted_token'] ?? null);

        return is_string($plain)
            && $plain !== ''
            && isset($existing['token'])
            && $this->hasher->check($plain, $existing['token']);
    }

    protected function decryptStoredToken(mixed $encrypted): ?string
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }

        return $plain !== '' ? $plain : null;
    }
}
