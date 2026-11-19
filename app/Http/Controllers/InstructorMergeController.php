<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InstructorMergePreviewRequest;
use App\Http\Requests\InstructorMergeRequest;
use App\Http\Requests\InstructorMergeSearchRequest;
use App\Models\Instructor;
use App\Services\InstructorMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InstructorMergeController extends Controller
{
    protected const SEARCH_RESULT_LIMIT = 25;

    public function __construct(
        protected InstructorMergeService $instructorMergeService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('InstructorMerges/Index', [
            'history' => $this->instructorMergeService->getMergeHistory()
                ->map(fn (Instructor $retired) => [
                    'id' => $retired->id,
                    'name' => $retired->user?->name,
                    'original_email' => $retired->merged_original_email,
                    'merged_into_id' => $retired->merged_into_instructor_id,
                    'merged_into_name' => $retired->mergedInto?->user?->name,
                    'merged_into_email' => $retired->mergedInto?->user?->email,
                    'merged_by_name' => $retired->mergedBy?->name,
                    'merged_at' => $retired->merged_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function searchInstructors(InstructorMergeSearchRequest $request): JsonResponse
    {
        $instructors = $this->instructorMergeService
            ->searchInstructors($request->validated('q'), self::SEARCH_RESULT_LIMIT)
            ->map(fn (Instructor $instructor) => $this->summary($instructor))
            ->values();

        return response()->json(['instructors' => $instructors]);
    }

    public function preview(InstructorMergePreviewRequest $request): JsonResponse
    {
        [$from, $into] = $this->pair($request);

        return response()->json([
            'from' => $this->summary($from),
            'into' => $this->summary($into),
        ] + $this->instructorMergeService->preview($from, $into, $request->boolean('take_email')));
    }

    public function store(InstructorMergeRequest $request): RedirectResponse
    {
        [$from, $into] = $this->pair($request);
        $fromEmail = $from->user->email;

        try {
            $this->instructorMergeService->mergeInstructors(
                $from,
                $into,
                $request->user(),
                $request->boolean('take_email'),
                $request->boolean('warnings_acknowledged'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['merge' => $exception->getMessage()]);
        }

        return redirect()->route('instructor-merges.index')
            ->with('success', "{$fromEmail} was merged into {$into->name} and deactivated.");
    }

    /**
     * @return array{0: Instructor, 1: Instructor}
     */
    private function pair(InstructorMergePreviewRequest $request): array
    {
        return [
            Instructor::with('user')->findOrFail($request->integer('from_instructor_id')),
            Instructor::with('user')->findOrFail($request->integer('into_instructor_id')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Instructor $instructor): array
    {
        return [
            'id' => $instructor->id,
            'name' => $instructor->name,
            'email' => $instructor->user?->email,
            'phone' => $instructor->phone,
            'status' => $instructor->status,
            'pupils_count' => $instructor->students_count ?? $instructor->students()->count(),
            'stripe_account_id' => $instructor->stripe_account_id,
            'stripe_connected' => $instructor->isStripeConnected(),
            'payouts_enabled' => (bool) $instructor->payouts_enabled,
            'created_at' => $instructor->created_at?->toIso8601String(),
        ];
    }
}
