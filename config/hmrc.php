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
        'itsa' => ['read:self-assessment', 'write:self-assessment'],
        'vat' => ['read:vat', 'write:vat'],
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

    /*
    |--------------------------------------------------------------------------
    | Fraud-prevention headers (Phase 2)
    |--------------------------------------------------------------------------
    |
    | DRIVE declares connection method WEB_APP_VIA_SERVER. These values feed
    | the Gov-Vendor-* headers and frame how Gov-Client-* values are composed.
    | See .claude/hmrc-fraud-headers.md for the per-header source of truth.
    */

    'fraud_headers' => [
        'connection_method' => 'WEB_APP_VIA_SERVER',
        'vendor_product_name' => env('HMRC_VENDOR_PRODUCT_NAME', 'Drive CRM'),
        'vendor_version' => env('HMRC_VENDOR_VERSION', '1.0.0'),
        'vendor_public_ip' => env('HMRC_VENDOR_PUBLIC_IP'),
        'user_id_key' => env('HMRC_USER_ID_KEY', 'drivecrm'),
        // Maximum age (minutes) for an HmrcClientFingerprint before an interactive
        // action must re-capture it. Keeps the device snapshot recent for HMRC.
        'fingerprint_max_age_minutes' => 30,
    ],

];
