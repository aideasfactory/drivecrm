<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    /**
     * Display the integrations index page.
     */
    public function index(): Response
    {
        return Inertia::render('Integrations/Index');
    }
}
