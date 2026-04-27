<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | HMRC environment
    |--------------------------------------------------------------------------
    |
    | Determines which HMRC endpoints are used. Sandbox endpoints are used for
    | development and integration testing; production endpoints require a
    | separate HMRC application and approved subscriptions.
    |
    | Supported: "sandbox", "production"
    */

    'environment' => env('HMRC_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | OAuth client credentials
    |--------------------------------------------------------------------------
    */

    'client_id' => env('HMRC_CLIENT_ID'),
    'client_secret' => env('HMRC_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | OAuth redirect URI
    |--------------------------------------------------------------------------
    |
    | Must match a redirect URI registered against the HMRC application. The
    | default resolves to <APP_URL>/hmrc/oauth/callback to match the route in
    | routes/web.php.
    */

    'redirect_uri' => env('HMRC_REDIRECT_URI', env('APP_URL').'/hmrc/oauth/callback'),

    /*
    |--------------------------------------------------------------------------
    | Environment-specific URLs
    |--------------------------------------------------------------------------
    */

    'urls' => [
        'sandbox' => [
            'auth' => 'https://test-www.tax.service.gov.uk/oauth/authorize',
            'token' => 'https://test-api.service.hmrc.gov.uk/oauth/token',
            'api' => 'https://test-api.service.hmrc.gov.uk',
        ],
        'production' => [
            'auth' => 'https://www.tax.service.gov.uk/oauth/authorize',
            'token' => 'https://api.service.hmrc.gov.uk/oauth/token',
            'api' => 'https://api.service.hmrc.gov.uk',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth scopes by feature
    |--------------------------------------------------------------------------
    |
    | Phase 1 only requests the `hello` scope (Hello World). Phase 3 will add
    | self-assessment scopes; Phase 4 will add VAT scopes.
    */

    'scopes' => [
        'hello_world' => ['hello'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token refresh buffer (seconds)
    |--------------------------------------------------------------------------
    |
    | Refresh the access token if it expires within this many seconds. Avoids
    | a race where a token returned valid is rejected by HMRC mid-request.
    */

    'access_token_refresh_buffer' => 60,

    /*
    |--------------------------------------------------------------------------
    | Refresh-expiry warning thresholds (days)
    |--------------------------------------------------------------------------
    |
    | The MonitorHmrcTokenExpiry command notifies instructors when their
    | refresh token is approaching expiry. Idempotency is enforced via
    | hmrc_tokens.last_expiry_warning_at.
    */

    'expiry_warning_days' => [30, 7],

    /*
    |--------------------------------------------------------------------------
    | Device identifier cookie
    |--------------------------------------------------------------------------
    |
    | Long-lived cookie used to mirror Gov-Client-Device-ID on the server. The
    | identifier persists across token disconnect/reconnect to comply with
    | HMRC fraud-prevention guidance.
    */

    'device_cookie' => [
        'name' => 'hmrc_device_id',
        'lifetime_minutes' => 60 * 24 * 365 * 10,
    ],

];
