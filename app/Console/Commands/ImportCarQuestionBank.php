<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MockTestService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Brings the mock test Car bank in line with the DVSA "Car (Cat B) QB"
 * spreadsheet. Safe to re-run: questions are matched by item code, and nothing
 * is deleted (Motorcycle/LGV-PCV/ADI rows stay in the table, unused).
 */
class ImportCarQuestionBank extends Command
{
    protected $signature = 'mock-tests:import-car-bank
                            {path? : Spreadsheet path (defaults to database/seeders/data/car-cat-b-qb-feb-2026.xlsx)}
                            {--dry-run : Report what would change without saving}';

    protected $description = 'Sync the mock test Car question bank from the DVSA Cat B spreadsheet';

    public function handle(MockTestService $mockTestService): int
    {
        $path = $this->argument('path') ?? database_path('seeders/data/car-cat-b-qb-feb-2026.xlsx');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $mockTestService->importCarQuestionBank($path, $dryRun);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing saved.');
        }

        $this->table(['', 'Questions'], [
            ['In spreadsheet', $result['rows']],
            ['Created', count($result['created'])],
            ['Updated', count($result['updated'])],
            ['  of which moved to Car', count($result['recategorised'])],
            ['Unchanged', $result['unchanged']],
            ['Invalid (skipped)', count($result['invalid'])],
        ]);

        if ($result['invalid'] !== []) {
            $this->warn('Skipped (no stem or answer not A-D): '.implode(', ', $result['invalid']));
        }

        if ($result['car_not_in_sheet'] !== []) {
            $this->warn('Car questions in the DB but not in the spreadsheet (left as they are): '.implode(', ', $result['car_not_in_sheet']));
        }

        return self::SUCCESS;
    }
}
