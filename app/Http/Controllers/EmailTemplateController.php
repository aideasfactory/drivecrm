<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EmailTemplateKey;
use App\Http\Requests\PreviewEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected EmailTemplateService $emailTemplateService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('EmailTemplates/Index', [
            'templates' => $this->emailTemplateService->list()->values(),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->emailTemplateService->update($emailTemplate, $request->validated());

        return back()->with('success', 'Email template saved.');
    }

    public function restore(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->emailTemplateService->restore($emailTemplate);

        return back()->with('success', 'Email template restored to the default copy.');
    }

    /**
     * Render the email with example values so staff can see how it looks.
     * Unsaved copy from the edit sheet can be sent to preview it before saving.
     */
    public function preview(PreviewEmailTemplateRequest $request, EmailTemplateKey $key): JsonResponse
    {
        return response()->json($this->emailTemplateService->preview(
            $key,
            $request->safe()->except('conditions'),
            $request->validated('conditions', []),
        ));
    }
}
