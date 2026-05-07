<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc\Vat;

use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitVatReturnRequest;
use App\Models\HmrcToken;
use App\Models\HmrcVatReturn;
use App\Services\HmrcVatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VatController extends Controller
{
    public function __construct(
        protected HmrcVatService $vat,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $instructor = $user->instructor;
        $token = HmrcToken::query()->where('user_id', $user->id)->first();
        $connected = $token !== null;
        $eligible = $instructor !== null
            && (bool) $instructor->vat_registered
            && is_string($instructor->vrn)
            && $instructor->vrn !== '';

        $scopes = is_array($token?->scopes) ? $token->scopes : [];
        $hasVatScope = in_array('write:vat', $scopes, true) && in_array('read:vat', $scopes, true);

        return Inertia::render('Hmrc/Vat/Index', [
            'connected' => $connected,
            'eligible' => $eligible,
            'hasVatScope' => $hasVatScope,
            'vrn' => $eligible ? $instructor->vrn : null,
            'openObligations' => $eligible ? $this->vat->openObligations($user)->map(fn ($o) => [
                'period_key' => $o->period_key,
                'period_start_date' => $o->period_start_date->toDateString(),
                'period_end_date' => $o->period_end_date->toDateString(),
                'due_date' => $o->due_date->toDateString(),
                'status' => $o->status->value,
                'days_until_due' => $o->daysUntilDue(),
            ])->all() : [],
            'history' => $eligible ? $this->vat->submissionHistory($user)->map(fn (HmrcVatReturn $r) => [
                'id' => $r->id,
                'period_key' => $r->period_key,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'form_bundle_number' => $r->form_bundle_number,
                'charge_ref_number' => $r->charge_ref_number,
                'payment_indicator' => $r->payment_indicator,
                'correlation_id' => $r->correlation_id,
                'total_vat_due' => $r->total_vat_due_pence / 100,
                'net_vat_due' => $r->net_vat_due_pence / 100,
            ])->all() : [],
        ]);
    }

    public function syncObligations(Request $request): RedirectResponse
    {
        try {
            $this->vat->syncObligations($request->user(), $this->fraudContextFor($request));
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->route('hmrc.vat.index')->with('error', $exception->getMessage());
        } catch (HmrcApiException $exception) {
            return redirect()->route('hmrc.vat.index')->with('error', $exception->userMessage());
        }

        return redirect()->route('hmrc.vat.index')->with('success', 'VAT obligations refreshed from HMRC.');
    }

    public function period(Request $request, string $periodKey): Response
    {
        $user = $request->user();

        $obligation = $this->vat->openObligations($user)
            ->first(fn ($o) => $o->period_key === $periodKey);

        $existing = HmrcVatReturn::query()
            ->where('user_id', $user->id)
            ->where('period_key', $periodKey)
            ->first();

        return Inertia::render('Hmrc/Vat/Period', [
            'periodKey' => $periodKey,
            'obligation' => $obligation ? [
                'period_start_date' => $obligation->period_start_date->toDateString(),
                'period_end_date' => $obligation->period_end_date->toDateString(),
                'due_date' => $obligation->due_date->toDateString(),
                'days_until_due' => $obligation->daysUntilDue(),
            ] : null,
            'existing' => $existing ? [
                'id' => $existing->id,
                'submitted_at' => $existing->submitted_at?->toIso8601String(),
                'form_bundle_number' => $existing->form_bundle_number,
                'charge_ref_number' => $existing->charge_ref_number,
                'correlation_id' => $existing->correlation_id,
            ] : null,
        ]);
    }

    public function store(SubmitVatReturnRequest $request, string $periodKey): RedirectResponse
    {
        try {
            $row = $this->vat->submitReturn(
                $request->user(),
                $periodKey,
                $request->validated(),
                $this->fraudContextFor($request),
            );
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->back()->with('error', $exception->getMessage())->withInput();
        } catch (HmrcApiException $exception) {
            return redirect()->back()->with('error', $exception->userMessage())->withInput();
        }

        $reference = $row->form_bundle_number ?? $row->correlation_id ?? '—';

        return redirect()
            ->route('hmrc.vat.index')
            ->with('success', "VAT return submitted to HMRC. Reference: {$reference}.");
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
}
