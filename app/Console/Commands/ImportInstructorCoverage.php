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
        {--instructor= : Instructor ID to import coverage for (single-instructor mode)}
        {--transmission= : Resolve instructor ID via config booking.instructor_ids (single-instructor mode)}
        {--by-transmission : Read transmission from column 1 of each CSV row and route into the three configured booking instructors}';

    protected $description = 'Import postcode-sector coverage rows from a CSV into the locations table. Single-instructor mode imports a flat list; --by-transmission mode aggregates per transmission and routes into the three configured booking instructors.';

    public function handle(): int
    {
        if ($this->option('by-transmission')) {
            return $this->handleByTransmission();
        }

        $instructorId = $this->resolveInstructorId();

        if ($instructorId <= 0) {
            $this->error('No instructor ID resolved. Pass --instructor=ID, --transmission=manual|automatic|both, or --by-transmission (with BOOKING_INSTRUCTOR_*_ID set in .env).');

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

    private function resolveInstructorId(): int
    {
        if ($this->option('instructor')) {
            return (int) $this->option('instructor');
        }

        $transmission = $this->option('transmission');
        if ($transmission) {
            $map = (array) config('booking.instructor_ids', []);

            return (int) ($map[$transmission] ?? 0);
        }

        return 0;
    }

    /**
     * Bulk import: aggregate sectors per transmission (column 1 of each row),
     * then route into the three configured booking instructors. Skips
     * duplicates within the file and against existing DB rows.
     */
    private function handleByTransmission(): int
    {
        $path = $this->argument('file');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        $map = (array) config('booking.instructor_ids', []);
        $instructorIds = [
            'manual' => (int) ($map['manual'] ?? 0),
            'automatic' => (int) ($map['automatic'] ?? 0),
            'both' => (int) ($map['both'] ?? 0),
        ];

        /** @var array<string, array<string, bool>> $aggregated */
        $aggregated = ['manual' => [], 'automatic' => [], 'both' => []];
        $invalid = [];
        $unknownLabels = [];

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Could not open file: {$path}");

            return self::FAILURE;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $key = $this->normaliseTransmissionLabel($row[0] ?? '');
            if ($key === null) {
                continue;
            }

            if (! array_key_exists($key, $aggregated)) {
                $unknownLabels[(string) ($row[0] ?? '')] = true;

                continue;
            }

            [$sectors, $rowInvalid] = $this->extractSectorsFromCsvField((string) ($row[1] ?? ''));
            foreach ($sectors as $sector) {
                $aggregated[$key][$sector] = true;
            }
            foreach ($rowInvalid as $bad) {
                $invalid[$bad] = true;
            }
        }
        fclose($handle);

        $totalInserted = 0;

        foreach ($aggregated as $key => $sectorSet) {
            $envName = 'BOOKING_INSTRUCTOR_'.strtoupper($key).'_ID';

            if ($sectorSet === []) {
                $this->line(sprintf('[%s] No rows in file.', $key));

                continue;
            }

            $instructorId = $instructorIds[$key];
            if ($instructorId <= 0) {
                $this->warn(sprintf('[%s] Skipping — %s is not set in .env.', $key, $envName));

                continue;
            }

            if (! Instructor::query()->whereKey($instructorId)->exists()) {
                $this->warn(sprintf('[%s] Skipping — instructor #%d (%s) not found.', $key, $instructorId, $envName));

                continue;
            }

            $sectors = array_keys($sectorSet);
            sort($sectors);

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
                $this->info(sprintf('[%s] Nothing to insert — %d sector(s) parsed, all already on instructor #%d.', $key, count($sectors), $instructorId));

                continue;
            }

            foreach (array_chunk($toInsert, 500) as $chunk) {
                Location::query()->insert($chunk);
            }

            $insertedCount = count($toInsert);
            $totalInserted += $insertedCount;

            $this->info(sprintf(
                '[%s] Instructor #%d: inserted %d new, skipped %d already-present (of %d unique parsed).',
                $key,
                $instructorId,
                $insertedCount,
                count($sectors) - $insertedCount,
                count($sectors),
            ));
        }

        if ($invalid !== []) {
            $this->warn(sprintf('Skipped %d invalid sector entr%s across the file:', count($invalid), count($invalid) === 1 ? 'y' : 'ies'));
            foreach (array_keys($invalid) as $entry) {
                $this->line('  - '.$entry);
            }
        }

        if ($unknownLabels !== []) {
            $this->warn('Rows skipped because the transmission label was unrecognised:');
            foreach (array_keys($unknownLabels) as $label) {
                $this->line('  - '.$label);
            }
        }

        $this->info(sprintf('Total new locations inserted: %d.', $totalInserted));

        return self::SUCCESS;
    }

    /**
     * Map a free-form transmission label onto one of: manual, automatic, both.
     * Returns null for blanks and the literal "Transmission" header row.
     */
    private function normaliseTransmissionLabel(string $raw): ?string
    {
        $clean = strtolower(trim($raw));

        if ($clean === '' || $clean === 'transmission') {
            return null;
        }

        return match (true) {
            $clean === 'manual' => 'manual',
            $clean === 'auto', $clean === 'automatic' => 'automatic',
            $clean === 'manual/auto', $clean === 'auto/manual', $clean === 'both' => 'both',
            default => $clean,
        };
    }

    /**
     * Parse a single CSV field containing a comma-separated list of postcode
     * sectors. Returns [validSectors, invalidEntries].
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function extractSectorsFromCsvField(string $raw): array
    {
        $sectors = [];
        $invalid = [];

        foreach (explode(',', $raw) as $entry) {
            $sector = strtoupper(trim($entry));
            if ($sector === '') {
                continue;
            }
            if (strlen($sector) > 10 || ! preg_match('/^[A-Z]{1,2}[0-9]{1,3}[A-Z]?$/', $sector)) {
                $invalid[$sector] = true;

                continue;
            }
            $sectors[$sector] = true;
        }

        return [array_keys($sectors), array_keys($invalid)];
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
