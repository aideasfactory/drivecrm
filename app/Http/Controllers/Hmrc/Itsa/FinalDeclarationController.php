<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hmrc\Itsa;

use App\Enums\ItsaCalculationType;
use App\Enums\ItsaSupplementaryType;
use App\Exceptions\Hmrc\HmrcApiException;
use App\Exceptions\Hmrc\MissingFraudFingerprintException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hmrc\Itsa\FinalDeclaration\SubmitFinalDeclarationRequest;
use App\Http\Requests\Hmrc\Itsa\FinalDeclaration\SubmitSupplementaryRequest;
use App\Models\HmrcItsaCalculation;
use App\Models\HmrcItsaSupplementaryData;
use App\Services\HmrcItsaFinalDeclarationService;
use App\Services\HmrcItsaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinalDeclarationController extends Controller
{
    public function __construct(
        protected HmrcItsaFinalDeclarationService $finalDeclaration,
        protected HmrcItsaService $itsa,
    ) {}

    public function index(Request $request, string $taxYear): Response
    {
        $user = $request->user();

        $supplementary = $this->finalDeclaration->getSupplementary($user, $taxYear);
        $existingDeclaration = $this->finalDeclaration->findFinalDeclaration($user, $taxYear);
        $calculations = $this->finalDeclaration->calculationsFor($user, $taxYear);

        $quarterlyHistory = $this->itsa->submissionHistory($user)
            ->filter(fn ($row) => $this->matchesTaxYear($row->period_start_date->toDateString(), $taxYear));

        return Inertia::render('Hmrc/Itsa/FinalDeclaration/Index', [
            'taxYear' => $taxYear,
            'steps' => $this->serialiseSteps($supplementary),
            'quarterly' => $quarterlyHistory->map(fn ($row) => [
                'id' => $row->id,
                'business_id' => $row->business_id,
                'period_key' => $row->period_key,
                'period_start_date' => $row->period_start_date->toDateString(),
                'period_end_date' => $row->period_end_date->toDateString(),
                'submission_id' => $row->submission_id,
                'submitted_at' => $row->submitted_at?->toIso8601String(),
                'turnover' => $row->turnover_pence / 100,
                'total_expenses' => $row->totalExpensesPence() / 100,
            ])->values()->all(),
            'finalDeclaration' => $existingDeclaration ? [
                'id' => $existingDeclaration->id,
                'submitted_at' => $existingDeclaration->submitted_at?->toIso8601String(),
                'correlation_id' => $existingDeclaration->correlation_id,
            ] : null,
            'calculations' => $calculations->map(fn (HmrcItsaCalculation $c) => [
                'id' => $c->id,
                'calculation_id' => $c->calculation_id,
                'type' => $c->calculation_type instanceof ItsaCalculationType ? $c->calculation_type->value : (string) $c->calculation_type,
                'status' => $c->status->value,
                'triggered_at' => $c->triggered_at->toIso8601String(),
                'processed_at' => $c->processed_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function step(Request $request, string $taxYear, string $type): Response
    {
        $supplementaryType = ItsaSupplementaryType::tryFrom($type);
        abort_if($supplementaryType === null, 404);

        $user = $request->user();
        $existing = $this->finalDeclaration->getSupplementary($user, $taxYear)[$supplementaryType->value] ?? null;

        return Inertia::render('Hmrc/Itsa/FinalDeclaration/Step', [
            'taxYear' => $taxYear,
            'type' => $supplementaryType->value,
            'label' => $supplementaryType->label(),
            'fields' => $supplementaryType->v1Fields(),
            'existing' => $existing ? [
                'payload' => $existing->payload,
                'submitted_at' => $existing->submitted_at?->toIso8601String(),
                'submission_id' => $existing->submission_id,
            ] : null,
        ]);
    }

    public function storeStep(SubmitSupplementaryRequest $request, string $taxYear, string $type): RedirectResponse
    {
        $supplementaryType = $request->resolveType();
        abort_if($supplementaryType === null, 404);

        try {
            $this->finalDeclaration->saveSupplementary(
                $request->user(),
                $taxYear,
                $supplementaryType,
                $request->validated(),
                $this->fraudContextFor($request),
            );
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->back()->with('error', $exception->getMessage())->withInput();
        } catch (HmrcApiException $exception) {
            return redirect()->back()->with('error', $exception->userMessage())->withInput();
        }

        return redirect()
            ->route('hmrc.itsa.final-declaration.index', ['taxYear' => $taxYear])
            ->with('success', "{$supplementaryType->label()} saved at HMRC.");
    }

    public function triggerCalculation(Request $request, string $taxYear): RedirectResponse
    {
        try {
            $calculation = $this->finalDeclaration->triggerCalculation(
                $request->user(),
                $taxYear,
                ItsaCalculationType::FinalDeclaration,
                $this->fraudContextFor($request),
            );

            $this->finalDeclaration->pollCalculation(
                $request->user(),
                $calculation,
                $this->fraudContextFor($request),
            );
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (HmrcApiException $exception) {
            return redirect()->back()->with('error', $exception->userMessage());
        }

        return redirect()->route('hmrc.itsa.final-declaration.calculation', [
            'taxYear' => $taxYear,
            'calculation' => $calculation->id,
        ]);
    }

    public function showCalculation(Request $request, string $taxYear, HmrcItsaCalculation $calculation): Response
    {
        abort_if($calculation->user_id !== $request->user()->id, 403);
        abort_if($calculation->tax_year !== $taxYear, 404);

        return Inertia::render('Hmrc/Itsa/FinalDeclaration/Calculation', [
            'taxYear' => $taxYear,
            'calculation' => [
                'id' => $calculation->id,
                'calculation_id' => $calculation->calculation_id,
                'status' => $calculation->status->value,
                'status_label' => $calculation->status->label(),
                'triggered_at' => $calculation->triggered_at->toIso8601String(),
                'processed_at' => $calculation->processed_at?->toIso8601String(),
                'summary_payload' => $calculation->summary_payload,
                'error_payload' => $calculation->error_payload,
            ],
            'finalDeclarationSubmitted' => $this->finalDeclaration->findFinalDeclaration(
                $request->user(),
                $taxYear,
            ) !== null,
        ]);
    }

    public function pollCalculation(Request $request, string $taxYear, HmrcItsaCalculation $calculation): JsonResponse
    {
        abort_if($calculation->user_id !== $request->user()->id, 403);
        abort_if($calculation->tax_year !== $taxYear, 404);

        try {
            $calculation = $this->finalDeclaration->refreshCalculation(
                $request->user(),
                $calculation,
                $this->fraudContextFor($request),
            );
        } catch (HmrcApiException $exception) {
            return response()->json(['error' => $exception->userMessage()], $exception->statusCode);
        }

        return response()->json([
            'status' => $calculation->status->value,
            'status_label' => $calculation->status->label(),
            'processed_at' => $calculation->processed_at?->toIso8601String(),
            'summary_payload' => $calculation->summary_payload,
            'error_payload' => $calculation->error_payload,
        ]);
    }

    public function submit(SubmitFinalDeclarationRequest $request, string $taxYear, HmrcItsaCalculation $calculation): RedirectResponse
    {
        abort_if($calculation->user_id !== $request->user()->id, 403);
        abort_if($calculation->tax_year !== $taxYear, 404);

        try {
            $declaration = $this->finalDeclaration->submitFinalDeclaration(
                $request->user(),
                $calculation,
                $this->fraudContextFor($request),
            );
        } catch (MissingFraudFingerprintException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (HmrcApiException $exception) {
            return redirect()->back()->with('error', $exception->userMessage());
        }

        return redirect()
            ->route('hmrc.itsa.final-declaration.index', ['taxYear' => $taxYear])
            ->with('success', 'Final declaration submitted to HMRC. Reference: '.($declaration->correlation_id ?? 'recorded'));
    }

    /**
     * @param  array<string, ?HmrcItsaSupplementaryData>  $supplementary
     * @return array<int, array<string, mixed>>
     */
    private function serialiseSteps(array $supplementary): array
    {
        $steps = [];
        foreach (ItsaSupplementaryType::cases() as $type) {
            $row = $supplementary[$type->value] ?? null;
            $steps[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'completed' => $row !== null,
                'submitted_at' => $row?->submitted_at?->toIso8601String(),
            ];
        }

        return $steps;
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

    private function matchesTaxYear(string $startDate, string $taxYear): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $taxYear, $matches)) {
            return false;
        }
        $start = "{$matches[1]}-04-06";
        $endYear = (int) $matches[1] + 1;
        $end = sprintf('%04d-04-05', $endYear);

        return $startDate >= $start && $startDate <= $end;
    }
}
