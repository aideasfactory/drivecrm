<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\HmrcItsaQuarterlyUpdateRevision;
use App\Models\HmrcVatReturn;
use App\Models\Instructor;
use Illuminate\Support\Carbon;

class WriteSubmissionsJsonAction
{
    /**
     * Write one JSON file per HMRC submission made during the tax year.
     *
     *  - `submissions/itsa/{period_key}.json` — ITSA quarterlies, with their
     *    request + response payloads, correlation IDs, and the full revision
     *    history (initial submission + any amendments).
     *  - `submissions/vat/{period_key}.json` — VAT 9-box returns (if any).
     *
     * Returns the count of JSON files written.
     */
    public function __invoke(string $stagingDir, Instructor $instructor, Carbon $start, Carbon $end): int
    {
        $base = $stagingDir.'/submissions';
        if (! is_dir($base) && ! mkdir($base, 0755, true) && ! is_dir($base)) {
            throw new \RuntimeException("Could not create directory: {$base}");
        }

        $count = 0;

        $count += $this->writeItsaSubmissions($base, $instructor, $start, $end);
        $count += $this->writeVatSubmissions($base, $instructor, $start, $end);

        return $count;
    }

    private function writeItsaSubmissions(string $base, Instructor $instructor, Carbon $start, Carbon $end): int
    {
        $dir = $base.'/itsa';
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Could not create directory: {$dir}");
        }

        $rows = HmrcItsaQuarterlyUpdate::query()
            ->where('instructor_id', $instructor->id)
            ->whereBetween('period_end_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('period_start_date')
            ->get();

        $written = 0;
        foreach ($rows as $row) {
            $revisions = HmrcItsaQuarterlyUpdateRevision::query()
                ->where('quarterly_update_id', $row->id)
                ->orderBy('revision_number')
                ->get()
                ->map(fn ($rev) => [
                    'revision_number' => $rev->revision_number,
                    'kind' => $rev->kind,
                    'submission_id' => $rev->submission_id,
                    'correlation_id' => $rev->correlation_id,
                    'submitted_at' => $rev->submitted_at?->toIso8601String(),
                    'request_payload' => $rev->request_payload,
                    'response_payload' => $rev->response_payload,
                ])
                ->all();

            $payload = [
                'business_id' => $row->business_id,
                'period_key' => $row->period_key,
                'period_start_date' => $row->period_start_date->toDateString(),
                'period_end_date' => $row->period_end_date->toDateString(),
                'submission_id' => $row->submission_id,
                'correlation_id' => $row->correlation_id,
                'submitted_at' => $row->submitted_at?->toIso8601String(),
                'current_state' => [
                    'turnover_pence' => $row->turnover_pence,
                    'other_income_pence' => $row->other_income_pence,
                    'consolidated_expenses_pence' => $row->consolidated_expenses_pence,
                ],
                'request_payload' => $row->request_payload,
                'response_payload' => $row->response_payload,
                'digital_records_attested_at' => $row->digital_records_attested_at?->toIso8601String(),
                'revisions' => $revisions,
            ];

            $filename = sprintf('%s_%s.json', $row->business_id, $this->safeFilename($row->period_key));
            file_put_contents($dir.'/'.$filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $written++;
        }

        return $written;
    }

    private function writeVatSubmissions(string $base, Instructor $instructor, Carbon $start, Carbon $end): int
    {
        if (! class_exists(HmrcVatReturn::class)) {
            return 0;
        }

        $rows = HmrcVatReturn::query()
            ->where('instructor_id', $instructor->id)
            ->whereBetween('submitted_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('submitted_at')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $dir = $base.'/vat';
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Could not create directory: {$dir}");
        }

        $written = 0;
        foreach ($rows as $row) {
            $payload = [
                'vrn' => $row->vrn,
                'period_key' => $row->period_key,
                'submitted_at' => $row->submitted_at?->toIso8601String(),
                'form_bundle_number' => $row->form_bundle_number,
                'charge_ref_number' => $row->charge_ref_number,
                'correlation_id' => $row->correlation_id,
                'boxes' => [
                    'box_1_vat_due_sales_pence' => $row->vat_due_sales_pence,
                    'box_2_vat_due_acquisitions_pence' => $row->vat_due_acquisitions_pence,
                    'box_3_total_vat_due_pence' => $row->total_vat_due_pence,
                    'box_4_vat_reclaimed_curr_period_pence' => $row->vat_reclaimed_curr_period_pence,
                    'box_5_net_vat_due_pence' => $row->net_vat_due_pence,
                    'box_6_total_value_sales_ex_vat_pence' => $row->total_value_sales_ex_vat_pence,
                    'box_7_total_value_purchases_ex_vat_pence' => $row->total_value_purchases_ex_vat_pence,
                    'box_8_total_value_goods_supplied_ex_vat_pence' => $row->total_value_goods_supplied_ex_vat_pence,
                    'box_9_total_acquisitions_ex_vat_pence' => $row->total_acquisitions_ex_vat_pence,
                ],
                'request_payload' => $row->request_payload,
                'response_payload' => $row->response_payload,
            ];

            $filename = sprintf('%s_%s.json', $row->vrn, $this->safeFilename($row->period_key));
            file_put_contents($dir.'/'.$filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $written++;
        }

        return $written;
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'unknown';
    }
}
