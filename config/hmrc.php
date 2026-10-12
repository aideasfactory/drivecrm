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
    | MTD digital button visibility
    |--------------------------------------------------------------------------
    |
    | Controls the "HMRC / Tax" (and "HMRC Connected") button in the instructor
    | layout header. Hidden by default; set SHOW_MTD_BUTTON=true in .env to
    | expose it. Also shared to the frontend via HandleInertiaRequests as
    | props.hmrc.show_mtd_button.
    */

    'show_mtd_button' => (bool) env('SHOW_MTD_BUTTON', false),

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
    | Hello World always. ITSA scopes are requested for instructors who are
    | not limited companies (including an incomplete tax profile). VAT
    | scopes are added when the tax profile is VAT-registered.
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
        'vendor_version_key' => env('HMRC_VENDOR_VERSION_KEY', env('HMRC_USER_ID_KEY', 'drivecrm')),
        'vendor_version' => env('HMRC_VENDOR_VERSION', '1.0.0'),
        // Public IP of the TLS edge the browser connects to (WAF, load balancer,
        // or the app server). Required for Gov-Vendor-Public-IP and Gov-Vendor-Forwarded.
        'vendor_public_ip' => env('HMRC_VENDOR_PUBLIC_IP'),
        // When set, hashed into Gov-Vendor-License-IDs. Leave empty when the
        // product has no license key on the originating device.
        'vendor_license_key' => env('HMRC_VENDOR_LICENSE_KEY'),
        'user_id_key' => env('HMRC_USER_ID_KEY', 'drivecrm'),
        // Request header a reverse proxy can set with the client's TCP source port.
        // REMOTE_PORT is only used when the TCP peer is the client itself.
        'client_public_port_header' => env('HMRC_CLIENT_PUBLIC_PORT_HEADER'),
        // Maximum age (minutes) for an HmrcClientFingerprint before an interactive
        // action must re-capture it. Keeps the device snapshot recent for HMRC.
        'fingerprint_max_age_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy IPs, or "*". Required behind a load balancer so
    | request()->ip() is the instructor's public address for Gov-Client-Public-IP.
    | Applied from bootstrap/app.php. Leave empty when the app is reached directly.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Vehicle defaults (Phase 6)
    |--------------------------------------------------------------------------
    |
    | actual_default_business_use_pct seeds the per-vehicle
    | business_use_percentage column on creation. Not UI-exposed in v1.
    | Locked to 95% based on the typical full-time ADI scenarios in
    | .claude/hmrc-tax-categories-client-summary.md.
    */

    'actual_default_business_use_pct' => env('HMRC_ACTUAL_DEFAULT_BUSINESS_USE_PCT', 95),

    /*
    |--------------------------------------------------------------------------
    | Simplified mileage allowance rates (Phase 7)
    |--------------------------------------------------------------------------
    |
    | HMRC's flat-rate mileage allowance. Unchanged since 2011 but kept as
    | config so a future rate change is a one-line edit. Pence per mile.
    | Threshold is per tax year (resets each 6 April), not per submission
    | period.
    */

    'mileage_allowance' => [
        'first_band_pence_per_mile' => 45,
        'second_band_pence_per_mile' => 25,
        'first_band_miles' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Year-end archive (Phase 9)
    |--------------------------------------------------------------------------
    |
    | Configuration for the per-instructor, per-tax-year ZIP that DRIVE generates
    | for end-of-year accountant handover. The download URL is a signed Laravel
    | route (no S3 dependency for v1). Retention years drives the daily pruning
    | command; HMRC currently requires 6 years from the end of the tax year.
    */

    'year_end_archive' => [
        'disk' => env('HMRC_ARCHIVE_DISK', 'local'),
        'path_template' => 'archives/{instructor_id}/{tax_year_start}.zip',
        'download_url_ttl_hours' => (int) env('HMRC_ARCHIVE_DOWNLOAD_URL_TTL_HOURS', 24),
        'retention_years' => (int) env('HMRC_ARCHIVE_RETENTION_YEARS', 6),
    ],

];
