<?php

declare(strict_types=1);

/*
 * Both app.just-drive.co.uk and app.drive-plus.co.uk are served by this
 * application. Stripe account links return to the canonical host, so login
 * and the dashboard on a legacy host must be redirected there. Every other
 * path — public booking, webhooks, and the mobile API — stays on the host
 * that received the request.
 *
 * `url` is the origin requests are sent to (scheme + host, no path).
 * `redirect_hosts` are the public hostnames that should give up /login and
 * /dashboard. Override either value when a non-production hostname needs
 * the same behaviour.
 */
return [
    'url' => env('APP_CANONICAL_URL', 'https://app.drive-plus.co.uk'),

    'redirect_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('APP_AUTH_REDIRECT_HOSTS', 'app.just-drive.co.uk')),
    ))),
];
