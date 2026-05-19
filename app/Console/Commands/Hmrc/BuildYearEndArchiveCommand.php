<?php

declare(strict_types=1);

namespace App\Console\Commands\Hmrc;

use App\Models\Instructor;
use App\Services\YearEndArchiveService;
use Illuminate\Console\Command;

class BuildYearEndArchiveCommand extends Command
{
    protected $signature = 'hmrc:build-year-end-archive
        {instructor : Instructor ID}
        {tax_year_start : Tax year start year (e.g. 2026 for 2026/27)}';

    protected $description = 'Queue a year-end ZIP archive build for one instructor/tax year combo.';

    public function handle(YearEndArchiveService $archives): int
    {
        $instructorId = (int) $this->argument('instructor');
        $taxYearStart = (int) $this->argument('tax_year_start');

        $instructor = Instructor::find($instructorId);
        if ($instructor === null) {
            $this->error("Instructor {$instructorId} not found.");

            return self::FAILURE;
        }

        $archive = $archives->queueBuild($instructor, $taxYearStart);

        $this->info(sprintf(
            'Queued archive #%d for instructor %d, tax year %s.',
            $archive->id,
            $instructorId,
            $archive->taxYearLabel(),
        ));

        return self::SUCCESS;
    }
}
