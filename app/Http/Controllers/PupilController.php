<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PupilController extends Controller
{
    /**
     * Display the pupils index page.
     */
    public function index(): Response
    {
        return Inertia::render('Pupils/Index');
    }
}
