<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class GetAppController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('GetApp', [
            'appName' => config('app.name'),
            'iosUrl' => $this->configuredStoreUrl(
                config('app_links.apple'),
                config('services.mobile_app.ios_url'),
            ),
            'androidUrl' => $this->configuredStoreUrl(
                config('app_links.android'),
                config('services.mobile_app.android_url'),
            ),
        ]);
    }

    /**
     * First store URL already configured for this platform.
     * An empty value keeps the matching button on "coming soon".
     */
    private function configuredStoreUrl(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
