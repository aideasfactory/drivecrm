<?php

declare(strict_types=1);

namespace App\Actions\Import;

use Generator;
use RuntimeException;

class ReadCsvColumnsAction
{
    /**
     * Stream a CSV one row at a time, keeping only the given columns (so a
     * huge file with hundreds of columns stays cheap). Headers are matched
     * lower-cased and trimmed (a UTF-8 BOM is stripped). Values are trimmed;
     * each row also gets `_line`, its record number in the file (header = 1).
     * Blank rows are dropped.
     *
     * @param  array<int, string>  $columns  Lower-case column names; all must exist
     * @return Generator<int, array<string, string|int>>
     *
     * @throws RuntimeException When the file can't be opened or a column is missing
     */
    public function __invoke(string $path, array $columns): Generator
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path}.");
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');

            if (! is_array($header)) {
                throw new RuntimeException("{$path} is empty.");
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $positions = array_flip(array_map(fn ($name) => strtolower(trim((string) $name)), $header));

            $columns = array_values(array_unique($columns));
            $missing = array_diff($columns, array_keys($positions));

            if ($missing !== []) {
                throw new RuntimeException("{$path} is missing column(s): ".implode(', ', $missing).'.');
            }

            $line = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;

                $row = [];

                foreach ($columns as $column) {
                    $row[$column] = trim((string) ($values[$positions[$column]] ?? ''));
                }

                if (implode('', $row) === '') {
                    continue;
                }

                $row['_line'] = $line;

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }
}
