<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\BuildYearEndArchiveJob;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\Instructor;
use App\Models\YearEndArchive;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class YearEndArchiveService extends BaseService
{
    /**
     * Archives a given instructor has on file, newest tax year first.
     *
     * @return Collection<int, YearEndArchive>
     */
    public function archivesFor(Instructor $instructor): Collection
    {
        $key = $this->cacheKey('instructor', $instructor->id, 'year_end_archives');

        return $this->remember(
            $key,
            fn () => $instructor->yearEndArchives()
                ->orderByDesc('tax_year_start')
                ->get(),
        );
    }

    /**
     * The list of tax years the instructor can generate. Includes every fully
     * completed UK tax year (6 Apr–5 Apr) since the earliest finance or
     * mileage row in the database, capped at the current tax year minus one.
     * Today's tax year is offered too but flagged as "in progress".
     *
     * @return array<int, array{tax_year_start: int, label: string, status: string}>
     */
    public function availableTaxYearsFor(Instructor $instructor): array
    {
        $now = Carbon::now()->startOfDay();
        $currentTaxYearStart = $now->month >= 4 && ($now->month > 4 || $now->day >= 6)
            ? $now->year
            : $now->year - 1;

        $earliestFinance = $instructor->finances()->orderBy('date')->value('date');
        $earliestMileage = $instructor->mileageLogs()->orderBy('date')->value('date');
        $candidates = array_filter([$earliestFinance, $earliestMileage]);

        if ($candidates === []) {
            $earliest = $currentTaxYearStart;
        } else {
            $earliestDate = Carbon::parse(min($candidates));
            $earliest = $earliestDate->month >= 4 && ($earliestDate->month > 4 || $earliestDate->day >= 6)
                ? $earliestDate->year
                : $earliestDate->year - 1;
        }

        $years = [];
        for ($y = $earliest; $y <= $currentTaxYearStart; $y++) {
            $years[] = [
                'tax_year_start' => $y,
                'label' => sprintf('%d/%s', $y, substr((string) ($y + 1), -2)),
                'status' => $y === $currentTaxYearStart ? 'in_progress' : 'complete',
            ];
        }

        return array_reverse($years);
    }

    /**
     * Cheap counts the UI shows in the pre-generation summary modal so the
     * instructor knows what's about to land in the ZIP.
     *
     * @return array{finances: int, mileage_logs: int, receipts: int, submissions: int}
     */
    public function summaryCountsFor(Instructor $instructor, int $taxYearStart): array
    {
        $start = Carbon::create($taxYearStart, 4, 6)->startOfDay();
        $end = Carbon::create($taxYearStart + 1, 4, 5)->endOfDay();

        $finances = (int) $instructor->finances()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $mileage = (int) $instructor->mileageLogs()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $receipts = (int) $instructor->finances()
            ->whereNotNull('receipt_path')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $submissions = (int) HmrcItsaQuarterlyUpdate::query()
            ->where('instructor_id', $instructor->id)
            ->whereBetween('period_end_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return [
            'finances' => $finances,
            'mileage_logs' => $mileage,
            'receipts' => $receipts,
            'submissions' => $submissions,
        ];
    }

    /**
     * Queue (or re-queue) an archive build for the given tax year. Re-queues by
     * resetting a stale failed/expired row rather than creating a duplicate, so
     * the (instructor_id, tax_year_start) unique constraint holds.
     */
    public function queueBuild(Instructor $instructor, int $taxYearStart): YearEndArchive
    {
        $archive = $instructor->yearEndArchives()
            ->where('tax_year_start', $taxYearStart)
            ->first();

        if ($archive === null) {
            $archive = $instructor->yearEndArchives()->create([
                'tax_year_start' => $taxYearStart,
                'status' => YearEndArchive::STATUS_QUEUED,
                'queued_at' => Carbon::now(),
            ]);
        } else {
            $archive->update([
                'status' => YearEndArchive::STATUS_QUEUED,
                'queued_at' => Carbon::now(),
                'error_message' => null,
                'generated_at' => null,
                'file_path' => null,
                'file_size_bytes' => null,
                'counts' => null,
                'purged_at' => null,
            ]);
        }

        $this->invalidateArchiveCache($instructor);

        BuildYearEndArchiveJob::dispatch($archive->id);

        return $archive;
    }

    public function invalidateArchiveCache(?Instructor $instructor): void
    {
        if ($instructor === null) {
            return;
        }

        $this->invalidate(
            $this->cacheKey('instructor', $instructor->id, 'year_end_archives')
        );
    }
}
