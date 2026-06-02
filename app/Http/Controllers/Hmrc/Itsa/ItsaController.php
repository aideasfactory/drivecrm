<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc\Itsa;

use App\Enums\ItsaEnrolmentStatus;
use App\Enums\ItsaExpenseCategory;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AmendQuarterlyUpdateRequest;
use App\Http\Requests\SubmitQuarterlyUpdateRequest;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\HmrcToken;
use App\Services\HmrcItsaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItsaController extends Controller
{
    public function __construct(
        protected HmrcItsaService $itsa,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Hmrc/Itsa/Index', $this->indexData($request));
    }

    /**
     * Build the data array used to render the ITSA index. Extracted so
     * InstructorController can embed the same payload inside the instructor
     * layout without duplicating the data-fetch logic.
     *
     * @return array<string, mixed>
     */
    public function indexData(Request $request): array
    {
        $user = $request->user();
        $instructor = $user->instructor;

        $connected = HmrcToken::query()->where('user_id', $user->id)->exists();
        $status = $instructor?->mtd_itsa_status ?? ItsaEnrolmentStatus::Unknown;

        return [
            'connected' => $connected,
            'enrolmentStatus' => [
                'value' => $status instanceof ItsaEnrolmentStatus ? $status->value : (string) $status,
                'label' => $status instanceof ItsaEnrolmentStatus ? $status->label() : ItsaEnrolmentStatus::Unknown->label(),
                'can_submit' => $status instanceof ItsaEnrolmentStatus ? $status->canSubmit() : false,
                'checked_at' => $instructor?->mtd_itsa_status_checked_at?->toIso8601String(),
            ],
            'businesses' => $this->itsa->cachedBusinesses($user)->map(fn ($b) => [
                'business_id' => $b->business_id,
                'type_of_business' => $b->type_of_business?->value ?? (string) $b->type_of_business,
                'trading_name' => $b->trading_name,
                'accounting_type' => $b->accounting_type,
                'commencement_date' => $b->commencement_date?->toDateString(),
                'cessation_date' => $b->cessation_date?->toDateString(),
            ])->all(),
            'openObligations' => $this->itsa->openObligations($user)->map(fn ($o) => [
                'business_id' => $o->business_id,
                'period_key' => $o->period_key,
                'period_start_date' => $o->period_start_date->toDateString(),
                'period_end_date' => $o->period_end_date->toDateString(),
                'due_date' => $o->due_date->toDateString(),
                'status' => $o->status->value,
                'days_until_due' => $o->daysUntilDue(),
            ])->all(),
            'history' => $this->itsa->submissionHistory($user)->map(fn ($h) => [
                'id' => $h->id,
                'business_id' => $h->business_id,
                'period_key' => $h->period_key,
                'period_start_date' => $h->period_start_date->toDateString(),
                'period_end_date' => $h->period_end_date->toDateString(),
                'submitted_at' => $h->submitted_at?->toIso8601String(),
                'submission_id' => $h->submission_id,
                'correlation_id' => $h->correlation_id,
                'turnover' => $h->turnover_pence / 100,
                'total_expenses' => $h->totalExpensesPence() / 100,
                'is_itemised' => $h->isItemised(),
            ])->all(),
        ];
    }

    public function refreshStatus(Request $request): RedirectResponse
    {
        $fallback = route('hmrc.itsa.index');

        try {
            $this->itsa->refreshEnrolmentStatus($request->user(), $this->fraudContextFor($request));
        } catch (MissingFraudFingerprintException $exception) {
            return back(fallback: $fallback)->with('error', $exception->getMessage());
        } catch (HmrcApiException $exception) {
            return back(fallback: $fallback)->with('error', $exception->userMessage());
        }

        return back(fallback: $fallback)->with('success', 'MTD ITSA enrolment status refreshed.');
    }

    public function syncObligations(Request $request): RedirectResponse
    {
        $fallback = route('hmrc.itsa.index');

        try {
            $this->itsa->syncObligations($request->user(), null, $this->fraudContextFor($request));
        } catch (MissingFraudFingerprintException $exception) {
            return back(fallback: $fallback)->with('error', $exception->getMessage());
        } catch (HmrcApiException $exception) {
            return back(fallback: $fallback)->with('error', $exception->userMessage());
        }

        return back(fallback: $fallback)->with('success', 'Obligations refreshed from HMRC.');
    }

    public function period(Request $request, string $businessId, string $periodKey): Response
    {
        return Inertia::render('Hmrc/Itsa/Period', $this->periodData($request, $businessId, $periodKey));
    }

    /**
     * Build the data array for the ITSA period detail view. Reused by
     * InstructorController when embedding the panel inside the instructor
     * layout via ?service=itsa&business=X&period=Y.
     *
     * @return array<string, mixed>
     */
    public function periodData(Request $request, string $businessId, string $periodKey): array
    {
        $user = $request->user();

        $obligation = $this->itsa->openObligations($user)
            ->first(fn ($o) => $o->business_id === $businessId && $o->period_key === $periodKey);

        $existing = HmrcItsaQuarterlyUpdate::query()
            ->where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->where('period_key', $periodKey)
            ->first();

        return [
            'businessId' => $businessId,
            'periodKey' => $periodKey,
            'obligation' => $obligation ? [
                'period_start_date' => $obligation->period_start_date->toDateString(),
                'period_end_date' => $obligation->period_end_date->toDateString(),
                'due_date' => $obligation->due_date->toDateString(),
                'days_until_due' => $obligation->daysUntilDue(),
            ] : null,
            'existing' => $existing ? $this->serialiseUpdate($existing) : null,
            'expenseCategories' => array_map(
                fn (ItsaExpenseCategory $c) => [
                    'value' => $c->value,
                    'label' => $c->label(),
                    'hmrc_key' => $c->hmrcKey(),
                ],
                ItsaExpenseCategory::cases(),
            ),
        ];
    }

    public function prefill(Request $request, string $businessId, string $periodKey): JsonResponse
    {
        $prefill = $this->itsa->prefillForPeriod($request->user(), $businessId, $periodKey);

        if ($prefill === null) {
            return response()->json(['error' => 'Obligation not found.'], 404);
        }

        return response()->json($prefill);
    }

    public function store(SubmitQuarterlyUpdateRequest $request, string $businessId, string $periodKey): RedirectResponse
    {
        try {
            $row = $this->itsa->submitQuarterly(
                $request->user(),
                $businessId,
                $periodKey,
                $request->validated(),
                $this->fraudContextFor($request),
            );
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->back()->with('error', $exception->getMessage())->withInput();
        } catch (HmrcApiException $exception) {
            return redirect()->back()->with('error', $exception->userMessage())->withInput();
        }

        return redirect($this->successRedirectTarget($request))
            ->with('success', "Quarterly update submitted to HMRC. Reference: {$row->submission_id}.");
    }

    public function amend(AmendQuarterlyUpdateRequest $request, HmrcItsaQuarterlyUpdate $quarterlyUpdate): RedirectResponse
    {
        if ($quarterlyUpdate->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $this->itsa->amendQuarterly(
                $request->user(),
                $quarterlyUpdate,
                $request->validated(),
                $this->fraudContextFor($request),
            );
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->back()->with('error', $exception->getMessage())->withInput();
        } catch (HmrcApiException $exception) {
            return redirect()->back()->with('error', $exception->userMessage())->withInput();
        }

        return redirect($this->successRedirectTarget($request))->with('success', 'Quarterly update amended at HMRC.');
    }

    /**
     * Resolve where to send the user after a successful submission. When the
     * embedded view supplies a `redirect_to` form field pointing at the
     * instructor profile's HMRC tab, honour it; otherwise default to the
     * standalone ITSA index. Validated against a strict path-prefix
     * whitelist to prevent open-redirect abuse.
     */
    private function successRedirectTarget(Request $request): string
    {
        $candidate = (string) $request->input('redirect_to', '');
        if ($candidate === '') {
            return route('hmrc.itsa.index');
        }

        // Only allow same-origin paths to /hmrc/itsa or
        // /instructors/{n}?tab=hmrc&service=itsa. Anything else falls back.
        $allowedPrefixes = ['/hmrc/itsa', '/instructors/'];
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($candidate, $prefix)) {
                return $candidate;
            }
        }

        return route('hmrc.itsa.index');
    }

    /**
     * @return array{ip: ?string, port: ?string, has_mfa: bool}
     */
    private function fraudContextFor(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'port' => $request->server('REMOTE_PORT'),
            'has_mfa' => (bool) $request->session()->get('two_factor_authenticated_at'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialiseUpdate(HmrcItsaQuarterlyUpdate $row): array
    {
        $expenses = [];
        foreach (ItsaExpenseCategory::cases() as $category) {
            $expenses[$category->value] = $row->{$category->column()};
        }

        return [
            'id' => $row->id,
            'submission_id' => $row->submission_id,
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'period_start_date' => $row->period_start_date->toDateString(),
            'period_end_date' => $row->period_end_date->toDateString(),
            'turnover_pence' => $row->turnover_pence,
            'other_income_pence' => $row->other_income_pence,
            'consolidated_expenses_pence' => $row->consolidated_expenses_pence,
            'expenses_pence' => $expenses,
            'is_itemised' => $row->isItemised(),
        ];
    }
}
