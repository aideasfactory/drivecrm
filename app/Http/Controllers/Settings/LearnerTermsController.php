<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LearnerTermsDraftRequest;
use App\Http\Requests\Settings\LearnerTermsPreviewRequest;
use App\Models\LearnerTermsVersion;
use App\Services\LearnerTermsService;
use App\Support\LearnerTermsMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * CRM Settings → Learner Terms: write, version and publish the DRIVE+ Learner
 * Terms shown in the student app. Full-access owners only.
 */
class LearnerTermsController extends Controller
{
    public function __construct(
        protected LearnerTermsService $learnerTermsService,
    ) {}

    public function edit(Request $request): Response
    {
        abort_if($request->user()->isRestrictedOwner(), 403, 'Your owner access does not include this setting.');

        $versions = $this->learnerTermsService->versions();
        $draft = $versions->first(fn (LearnerTermsVersion $version) => ! $version->isPublished());
        $published = $versions->filter(fn (LearnerTermsVersion $version) => $version->isPublished())->values();

        return Inertia::render('settings/LearnerTerms', [
            'draft' => $draft ? [
                'id' => $draft->id,
                'version' => $draft->version,
                'body' => $draft->body,
                'updated_at' => $draft->updated_at?->toIso8601String(),
            ] : null,
            'versions' => $published->map(fn (LearnerTermsVersion $version) => [
                'id' => $version->id,
                'version' => $version->version,
                'body' => $version->body,
                'html' => LearnerTermsMarkdown::toHtml($version->body),
                'published_at' => $version->published_at?->toIso8601String(),
                'published_by' => $version->publishedBy?->name,
                'acceptances_count' => $version->acceptances_count,
            ])->values(),
        ]);
    }

    public function saveDraft(LearnerTermsDraftRequest $request): RedirectResponse
    {
        $this->learnerTermsService->saveDraft(
            (string) $request->validated('version'),
            (string) $request->validated('body'),
            $request->user(),
        );

        return back();
    }

    public function publish(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner() && ! $request->user()->isRestrictedOwner(), 403);

        try {
            $this->learnerTermsService->publishDraft($request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyDraft(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner() && ! $request->user()->isRestrictedOwner(), 403);

        $this->learnerTermsService->deleteDraft();

        return back();
    }

    /**
     * Render Markdown to HTML for the editor's preview tab.
     */
    public function preview(LearnerTermsPreviewRequest $request): JsonResponse
    {
        return response()->json([
            'html' => LearnerTermsMarkdown::toHtml($request->validated('body')),
        ]);
    }
}
