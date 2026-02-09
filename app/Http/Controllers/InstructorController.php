<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class InstructorController extends Controller
{
    /**
     * Display the instructors index page.
     */
    public function index(): Response
    {
        return Inertia::render('Instructors/Index');
    }
}
