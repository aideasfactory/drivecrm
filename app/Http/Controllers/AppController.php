<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AppController extends Controller
{
    /**
     * Display the apps index page.
     */
    public function index(): Response
    {
        return Inertia::render('Apps/Index');
    }
}
