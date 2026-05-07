<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Models\HmrcOAuthState;
use App\Models\User;
use Illuminate\Support\Str;

class BuildAuthorizationUrlAction
{
    /**
     * Build the authorisation URL for HMRC's OAuth flow.
     *
     * Persists the state + PKCE verifier so the callback can validate the
     * round trip. Returns the absolute URL the browser should be redirected to.
     *
     * @param  array<int, string>  $scopes
     */
    public function __invoke(User $user, array $scopes): string
    {
        $state = Str::random(64);
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->codeChallengeFor($codeVerifier);
        $redirectUri = (string) config('hmrc.redirect_uri');

        HmrcOAuthState::query()->create([
            'user_id' => $user->id,
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'scopes' => $scopes,
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $environment = (string) config('hmrc.environment', 'sandbox');
        $base = (string) config("hmrc.urls.{$environment}.auth");

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('hmrc.client_id'),
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return "{$base}?{$query}";
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function codeChallengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
