<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Models\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CopyReceiptsToArchiveAction
{
    /**
     * Copy every receipt attached to a finance row in the date range into
     * `receipts/Q{1..4}/{date}_{financeId}__{originalName}` under the staging
     * directory. Bucketing is by tax-year quarter (Q1 = 6 Apr–5 Jul, etc.).
     *
     * Failures on individual receipts are logged but do not abort the archive
     * build — a missing receipt should not prevent the rest of the year's data
     * from reaching the accountant.
     *
     * Returns the count of receipts successfully copied.
     */
    public function __invoke(string $stagingDir, Instructor $instructor, Carbon $start, Carbon $end): int
    {
        $receiptsDir = $stagingDir.'/receipts';
        if (! is_dir($receiptsDir) && ! mkdir($receiptsDir, 0755, true) && ! is_dir($receiptsDir)) {
            throw new \RuntimeException("Could not create directory: {$receiptsDir}");
        }

        $copied = 0;

        $rows = $instructor->finances()
            ->whereNotNull('receipt_path')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get(['id', 'date', 'description', 'receipt_path', 'receipt_original_name']);

        foreach ($rows as $row) {
            $quarter = $this->quarterFor(Carbon::parse((string) $row->date), $start);
            $quarterDir = $receiptsDir.'/Q'.$quarter;
            if (! is_dir($quarterDir) && ! mkdir($quarterDir, 0755, true) && ! is_dir($quarterDir)) {
                Log::warning('Year-end archive: could not create quarter dir', ['dir' => $quarterDir]);

                continue;
            }

            try {
                $contents = Storage::disk('s3')->get($row->receipt_path);
            } catch (\Throwable $e) {
                Log::warning('Year-end archive: could not fetch receipt', [
                    'finance_id' => $row->id,
                    'receipt_path' => $row->receipt_path,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($contents === null || $contents === '') {
                Log::warning('Year-end archive: receipt was empty', ['finance_id' => $row->id]);

                continue;
            }

            $safeOriginal = $this->safeFilename((string) ($row->receipt_original_name ?: 'receipt'));
            $filename = sprintf('%s_%d__%s', $row->date, $row->id, $safeOriginal);
            $targetPath = $quarterDir.'/'.$filename;

            if (file_put_contents($targetPath, $contents) === false) {
                Log::warning('Year-end archive: could not write receipt', ['target' => $targetPath]);

                continue;
            }

            $copied++;
        }

        return $copied;
    }

    /**
     * Tax-year quarter (1..4) for the given date, where Q1 starts at the
     * tax-year start (6 April).
     */
    private function quarterFor(Carbon $date, Carbon $taxYearStart): int
    {
        $monthsFromStart = $taxYearStart->diffInMonths($date);

        return min(4, (int) floor($monthsFromStart / 3) + 1);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'receipt';

        return substr($name, 0, 100);
    }
}
