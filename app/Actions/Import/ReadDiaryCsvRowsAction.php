<?php

declare(strict_types=1);

namespace App\Actions\Import;

use Generator;
use RuntimeException;

class ReadDiaryCsvRowsAction
{
    /** Columns read from each row; any other column in the file is ignored. */
    public const COLUMNS = [
        'diary_ref',
        'instructor_ref',
        'student_ref',
        'date',
        'start_time',
        'end_time',
        'status',
    ];

    public function __construct(
        protected ReadCsvColumnsAction $readCsvColumns,
    ) {}

    /**
     * Stream a CSV of diary rows (e.g. the legacy system's lesson_progress.csv,
     * which has these columns plus ~1,800 progress columns), keeping only
     * COLUMNS plus any $extraColumns (see ReadCsvColumnsAction).
     *
     * @param  array<int, string>  $extraColumns  Further columns to keep (lower-case), e.g. `notes`; must exist
     * @return Generator<int, array<string, string|int>>
     *
     * @throws RuntimeException When the file can't be opened or a column is missing
     */
    public function __invoke(string $path, array $extraColumns = []): Generator
    {
        return ($this->readCsvColumns)($path, [...self::COLUMNS, ...$extraColumns]);
    }
}
