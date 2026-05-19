<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Enums\ItsaExpenseCategory;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\HmrcVatReturn;
use App\Models\Instructor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RenderArchiveCoverSheetPdfAction
{
    /**
     * Render `summary.pdf` into the staging directory.
     *
     * @param  array{finances: int, mileage_logs: int, receipts: int, submissions: int}  $counts
     */
    public function __invoke(
        string $stagingDir,
        Instructor $instructor,
        Carbon $taxYearStart,
        Carbon $taxYearEnd,
        array $counts,
    ): void {
        $turnoverPence = (int) DB::table('lessons')
            ->where('instructor_id', $instructor->id)
            ->whereBetween('date', [$taxYearStart->toDateString(), $taxYearEnd->toDateString()])
            ->where('status', 'completed')
            ->sum('amount_pence');

        $totalExpensesPence = (int) $instructor->finances()
            ->where('type', 'expense')
            ->whereBetween('date', [$taxYearStart->toDateString(), $taxYearEnd->toDateString()])
            ->sum('amount_pence');

        $totalBusinessMiles = (int) $instructor->mileageLogs()
            ->where('type', 'business')
            ->whereBetween('date', [$taxYearStart->toDateString(), $taxYearEnd->toDateString()])
            ->sum('miles');

        $bucketTotals = $this->bucketTotals($instructor, $taxYearStart, $taxYearEnd);

        $vehicles = $instructor->vehicles()
            ->where(function ($q) use ($taxYearStart) {
                $q->whereNull('disposed_on')->orWhere('disposed_on', '>=', $taxYearStart->toDateString());
            })
            ->orderBy('display_name')
            ->get()
            ->map(fn ($v) => [
                'display_name' => $v->display_name,
                'registration' => $v->registration,
                'method_label' => $v->method->label(),
                'business_use_percentage' => $v->business_use_percentage,
                'acquired_on' => $v->acquired_on?->toDateString(),
                'disposed_on' => $v->disposed_on?->toDateString(),
            ])
            ->all();

        $submissions = $this->buildSubmissionsList($instructor, $taxYearStart, $taxYearEnd);

        $user = $instructor->user;

        $pdf = Pdf::loadView('pdf.year-end-archive-cover', [
            'taxYearLabel' => sprintf('%d/%s', $taxYearStart->year, substr((string) ($taxYearStart->year + 1), -2)),
            'taxYearStart' => $taxYearStart,
            'taxYearEnd' => $taxYearEnd,
            'generatedAt' => Carbon::now(),
            'instructorName' => $user?->name ?? $instructor->display_name ?? 'Instructor',
            'instructorEmail' => $user?->email ?? '—',
            'utr' => $instructor->utr,
            'nino' => $instructor->nino,
            'turnoverPence' => $turnoverPence,
            'totalExpensesPence' => $totalExpensesPence,
            'totalBusinessMiles' => $totalBusinessMiles,
            'bucketTotals' => $bucketTotals,
            'vehicles' => $vehicles,
            'submissions' => $submissions,
            'counts' => $counts,
        ]);

        $pdf->save($stagingDir.'/summary.pdf');
    }

    /**
     * @return array<string, int>  bucketLabel => pence
     */
    private function bucketTotals(Instructor $instructor, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('instructor_finances')
            ->join('category_tax_mapping', 'category_tax_mapping.category', '=', 'instructor_finances.category')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'instructor_finances.vehicle_id')
            ->where('instructor_finances.instructor_id', $instructor->id)
            ->where('instructor_finances.type', 'expense')
            ->where('category_tax_mapping.claimable', true)
            ->whereNotNull('category_tax_mapping.itsa_bucket')
            ->whereBetween('instructor_finances.date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) {
                // Exclude method-dependent rows that are on a Simplified vehicle
                // (mileage allowance covers them — see vehicles-and-method-choice.md).
                $q->where('category_tax_mapping.method_dependent', false)
                    ->orWhereNotIn('vehicles.method', ['simplified']);
            })
            ->groupBy('category_tax_mapping.itsa_bucket')
            ->selectRaw('category_tax_mapping.itsa_bucket as bucket, SUM(instructor_finances.amount_pence) as pence_total')
            ->get();

        $labelByKey = [];
        foreach (ItsaExpenseCategory::cases() as $cat) {
            $labelByKey[$cat->hmrcKey()] = $cat->label();
        }

        $out = [];
        foreach ($rows as $row) {
            $label = $labelByKey[(string) $row->bucket] ?? (string) $row->bucket;
            $out[$label] = (int) $row->pence_total;
        }

        return $out;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function buildSubmissionsList(Instructor $instructor, Carbon $start, Carbon $end): array
    {
        $list = [];

        $itsa = HmrcItsaQuarterlyUpdate::query()
            ->where('instructor_id', $instructor->id)
            ->whereBetween('period_end_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('period_start_date')
            ->get();

        foreach ($itsa as $row) {
            $list[] = [
                'kind' => 'ITSA quarterly',
                'period' => $row->period_start_date->toDateString().' – '.$row->period_end_date->toDateString(),
                'submitted_at' => $row->submitted_at?->toIso8601String() ?? '—',
                'submission_id' => $row->submission_id,
                'correlation_id' => $row->correlation_id,
            ];
        }

        if (class_exists(HmrcVatReturn::class)) {
            $vat = HmrcVatReturn::query()
                ->where('instructor_id', $instructor->id)
                ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->orderBy('submitted_at')
                ->get();

            foreach ($vat as $row) {
                $list[] = [
                    'kind' => 'VAT return',
                    'period' => $row->period_key,
                    'submitted_at' => $row->submitted_at?->toIso8601String() ?? '—',
                    'submission_id' => $row->form_bundle_number,
                    'correlation_id' => $row->correlation_id,
                ];
            }
        }

        return $list;
    }
}
