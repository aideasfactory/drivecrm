<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class ReplaceInstructorLocationsAction
{
    /**
     * Postcode sector format, matching StoreLocationRequest.
     */
    private const SECTOR_PATTERN = '/^[A-Z]{1,2}[0-9]{1,2}$/';

    /**
     * Replace all coverage locations for an instructor from parsed CSV rows.
     *
     * Validates each row's postcode_sector, then transactionally deletes the
     * instructor's existing locations and inserts the valid set. If no rows
     * are valid, the existing locations are left untouched.
     *
     * @param  array<int, array<string, string>>  $rows  Parsed CSV rows keyed by header
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function __invoke(Instructor $instructor, array $rows): array
    {
        $sectors = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            // Row 1 is the header, so data rows start at 2
            $rowNumber = $index + 2;

            $sector = strtoupper(trim($row['postcode_sector'] ?? ''));

            if ($sector === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'postcode_sector',
                    'message' => 'Postcode sector is required.',
                ];

                continue;
            }

            if (! preg_match(self::SECTOR_PATTERN, $sector)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'postcode_sector',
                    'message' => "'{$sector}' is not a valid postcode sector (e.g., TS7, WR14, M1).",
                ];

                continue;
            }

            if (in_array($sector, $sectors, true)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'postcode_sector',
                    'message' => "'{$sector}' is duplicated in the file.",
                ];

                continue;
            }

            $sectors[] = $sector;
        }

        if ($sectors === []) {
            return [
                'imported' => 0,
                'skipped' => count($errors),
                'errors' => $errors,
            ];
        }

        sort($sectors);

        DB::transaction(function () use ($instructor, $sectors): void {
            $instructor->locations()->delete();

            $now = now();

            Location::insert(array_map(fn (string $sector) => [
                'instructor_id' => $instructor->id,
                'postcode_sector' => $sector,
                'created_at' => $now,
                'updated_at' => $now,
            ], $sectors));
        });

        return [
            'imported' => count($sectors),
            'skipped' => count($errors),
            'errors' => $errors,
        ];
    }
}
