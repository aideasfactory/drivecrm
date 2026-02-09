<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display the reports index page.
     */
    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }
}
