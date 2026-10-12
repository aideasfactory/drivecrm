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
            'iosUrl' => config('app_links.apple') ?: null,
            'androidUrl' => config('app_links.android') ?: null,
        ]);
    }
}
