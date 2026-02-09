<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    /**
     * Display the resources index page.
     */
    public function index(): Response
    {
        return Inertia::render('Resources/Index');
    }
}
