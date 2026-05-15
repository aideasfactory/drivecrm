<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Instructor;
use App\Models\Location;
use Illuminate\Console\Command;

class ImportInstructorCoverage extends Command
{
    protected $signature = 'booking:import-coverage
        {file=postocdes .csv : Path to the CSV file (relative to project root or absolute)}
        {--instructor= : Override instructor ID (defaults to config booking.instructor_id)}';

    protected $description = 'Import postcode-sector coverage rows from a CSV into the locations table for the booking instructor.';

    public function handle(): int
    {
        $instructorId = $this->option('instructor')
            ? (int) $this->option('instructor')
            : (int) config('booking.instructor_id');

        if ($instructorId <= 0) {
            $this->error('No instructor ID resolved. Set BOOKING_INSTRUCTOR_ID in .env or pass --instructor=ID.');

            return self::FAILURE;
        }

        if (! Instructor::query()->whereKey($instructorId)->exists()) {
            $this->error("Instructor #{$instructorId} not found.");

            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        [$sectors, $invalid] = $this->extractSectors($path);
        $this->info(sprintf('Parsed %d unique postcode sectors from CSV.', count($sectors)));
        if ($invalid !== []) {
            $this->warn(sprintf('Skipped %d invalid entr%s:', count($invalid), count($invalid) === 1 ? 'y' : 'ies'));
            foreach ($invalid as $entry) {
                $this->line('  - '.$entry);
            }
        }

        $existing = Location::query()
            ->where('instructor_id', $instructorId)
            ->pluck('postcode_sector')
            ->all();
        $existingSet = array_flip($existing);

        $toInsert = [];
        $now = now();
        foreach ($sectors as $sector) {
            if (isset($existingSet[$sector])) {
                continue;
            }
            $toInsert[] = [
                'instructor_id' => $instructorId,
                'postcode_sector' => $sector,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($toInsert === []) {
            $this->info('Nothing to insert — every sector is already on this instructor.');

            return self::SUCCESS;
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            Location::query()->insert($chunk);
        }

        $this->info(sprintf(
            'Inserted %d new location(s) for instructor #%d. Skipped %d already-present.',
            count($toInsert),
            $instructorId,
            count($sectors) - count($toInsert),
        ));

        return self::SUCCESS;
    }

    /**
     * Read the CSV and return [validSectors, invalidEntries].
     * Valid sectors: 1–2 letters + 1–3 digits, optional trailing letter, max 10 chars.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function extractSectors(string $path): array
    {
        $sectors = [];
        $invalid = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = trim($line, "\"' \t\r\n");

            foreach (explode(',', $line) as $raw) {
                $sector = strtoupper(trim($raw));
                if ($sector === '') {
                    continue;
                }
                if (strlen($sector) > 10 || ! preg_match('/^[A-Z]{1,2}[0-9]{1,3}[A-Z]?$/', $sector)) {
                    $invalid[$sector] = true;

                    continue;
                }
                $sectors[$sector] = true;
            }
        }
        fclose($handle);

        $unique = array_keys($sectors);
        sort($unique);

        return [$unique, array_keys($invalid)];
    }
}
