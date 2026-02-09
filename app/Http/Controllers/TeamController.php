<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display the teams index page.
     */
    public function index(): Response
    {
        return Inertia::render('Teams/Index');
    }
}
