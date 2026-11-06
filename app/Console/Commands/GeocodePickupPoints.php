<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StudentService;
use Illuminate\Console\Command;
use RuntimeException;

class GeocodePickupPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pickup-points:geocode
        {--dry-run : Only count the points and postcodes still missing coordinates}
        {--batch=100 : Points per postcodes.io bulk request (max 100)}
        {--pause=2 : Seconds to wait between requests}
        {--limit= : Stop after this many points}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill latitude/longitude on pupil pick-up points that have none (e.g. imported ones) from their postcode via postcodes.io, in batches with a pause between requests. Points that already have coordinates are never touched. Safe to stop and re-run.';

    public function handle(StudentService $studentService): int
    {
        foreach (['batch', 'pause', 'limit'] as $option) {
            $value = $this->option($option);

            if ($value !== null && (! ctype_digit((string) $value) || ($option !== 'pause' && (int) $value < 1))) {
                $this->error("--{$option} must be a whole number".($option === 'pause' ? '.' : ' of 1 or more.'));

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $counts = $studentService->geocodePickupPoints(
                (int) $this->option('batch'),
                (int) $this->option('pause'),
                $this->option('limit') !== null ? (int) $this->option('limit') : null,
                $dryRun,
                fn (string $line) => $this->line($line),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage().' Points done so far are saved; run the command again to carry on.');

            return self::FAILURE;
        }

        $this->table(['', 'Count'], [
            ['Pick-up points missing coordinates', $counts['points_missing']],
            ['  …distinct postcodes', $counts['postcodes_missing']],
            [$dryRun ? 'Requests it would make' : 'Requests made', $counts['batches']],
            ['Geocoded', $counts['geocoded']],
            ['Postcode not found by postcodes.io (left empty)', $counts['not_found']],
        ]);

        $this->line($dryRun ? 'Dry run — nothing was changed and postcodes.io was not called.' : 'Done.');

        return self::SUCCESS;
    }
}
