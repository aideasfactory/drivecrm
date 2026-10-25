<?php

declare(strict_types=1);

namespace App\Actions\Import;

use RuntimeException;
use ZipArchive;

class ReadImportBundleAction
{
    /**
     * The CSV files an import bundle may contain, keyed by bundle section.
     * Every file is optional; a bundle must contain at least one.
     */
    public const FILES = [
        'instructors' => 'instructors.csv',
        'coverage' => 'coverage.csv',
        'students' => 'students.csv',
        'diary' => 'diary.csv',
        'finances' => 'finances.csv',
    ];

    /**
     * Read a zip of CSVs into rows per section, plus an `attachments` index of
     * every other file in the zip (e.g. receipts/F-5002.pdf) keyed by lower-cased
     * basename: `['f-5002.pdf' => ['name' => entry name in zip, 'size' => bytes]]`.
     * A basename that appears twice maps to null (ambiguous).
     *
     * Headers are lower-cased and trimmed (a UTF-8 BOM is stripped). Each row is
     * an associative array of trimmed strings plus `_line`, its line number in
     * the source file, for error reporting. Fully blank rows are dropped.
     * Files are matched by basename, so a zip with a wrapping folder works.
     *
     * @return array<string, array<int|string, mixed>>
     *
     * @throws RuntimeException When the zip cannot be opened or holds no known CSV
     */
    public function __invoke(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open zip: {$zipPath}");
        }

        $bundle = array_fill_keys(array_keys(self::FILES), []);
        $bundle['attachments'] = [];
        $found = false;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $basename = strtolower(basename($name));

                if (str_starts_with($name, '__MACOSX/') || str_ends_with($name, '/')) {
                    continue;
                }

                $section = array_search($basename, self::FILES, true);

                if ($section === false) {
                    $bundle['attachments'][$basename] = array_key_exists($basename, $bundle['attachments'])
                        ? null
                        : ['name' => $name, 'size' => (int) $zip->statIndex($i)['size']];

                    continue;
                }

                $stream = $zip->getStreamIndex($i);

                if ($stream === false) {
                    throw new RuntimeException("Could not read {$name} from the zip.");
                }

                try {
                    $bundle[$section] = $this->parseCsv($stream);
                } finally {
                    fclose($stream);
                }
                $found = true;
            }
        } finally {
            $zip->close();
        }

        if (! $found) {
            throw new RuntimeException('The zip contains none of: '.implode(', ', self::FILES).'.');
        }

        return $bundle;
    }

    /**
     * Parse a CSV straight from the zip entry's stream, so the file is never
     * held in memory as a whole alongside its rows.
     *
     * @param  resource  $handle
     * @return array<int, array<string, string|int>>
     */
    protected function parseCsv($handle): array
    {
        $headers = null;
        $rows = [];
        $line = 0;

        while (($record = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            if ($headers === null) {
                $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($record[0] ?? '')) ?? $record[0];
                $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $record);

                continue;
            }

            $values = array_map(fn ($value) => trim((string) $value), $record);

            if (implode('', $values) === '') {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$index] ?? '';
                }
            }

            $row['_line'] = $line;
            $rows[] = $row;
        }

        return $rows;
    }
}
