<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EmailTemplateService;
use App\Services\WorkflowService;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService,
        protected EmailTemplateService $emailTemplateService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Workflows/Index', [
            'workflows' => $this->workflowService->list(),
            'templates' => $this->emailTemplateService->list()->values(),
        ]);
    }
}
