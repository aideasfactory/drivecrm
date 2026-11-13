<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportRun;
use App\Support\ImportBundleLayout;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class StageImportBundleAction
{
    protected const INSERT_BATCH = 1000;

    /**
     * Read every bundle CSV in the zip into import_staged_rows for this run,
     * one streamed row at a time (a file is never held in memory whole).
     *
     * Headers are lower-cased and trimmed (UTF-8 BOM stripped); values are
     * trimmed; fully blank rows are dropped. A file missing a required column
     * is not staged and is reported as an error. Rows of files without an
     * instructor_ref take their pupil's instructor from students.csv.
     *
     * Any rows already staged for the run are removed first, so a retried
     * job starts clean.
     *
     * @return array{files: array<string, array{entry: string, records: int, staged: int}>, errors: array<int, array{file: string, line: int, message: string}>}
     *
     * @throws RuntimeException When the zip can't be opened or holds no bundle CSV
     */
    public function __invoke(ImportRun $run, string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the zip file.');
        }

        DB::table('import_staged_rows')->where('import_run_id', $run->id)->delete();

        $files = [];
        $errors = [];
        $studentInstructors = [];

        try {
            $entries = $this->bundleEntries($zip);

            if ($entries === []) {
                throw new RuntimeException('The zip contains none of: '.implode(', ', ImportBundleLayout::FILES).'.');
            }

            foreach (array_keys(ImportBundleLayout::FILES) as $section) {
                if (! isset($entries[$section])) {
                    continue;
                }

                $stream = $zip->getStream($entries[$section]);

                if ($stream === false) {
                    throw new RuntimeException("Could not read {$entries[$section]} from the zip.");
                }

                try {
                    $result = $this->stageFile($run, $section, $stream, $studentInstructors);
                } finally {
                    fclose($stream);
                }

                $files[$section] = ['entry' => $entries[$section]] + $result['counts'];
                array_push($errors, ...$result['errors']);
            }
        } finally {
            $zip->close();
        }

        return ['files' => $files, 'errors' => $errors];
    }

    /**
     * Section => zip entry name, for each bundle CSV in the zip.
     *
     * @return array<string, string>
     */
    protected function bundleEntries(ZipArchive $zip): array
    {
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, '__MACOSX/') || str_ends_with($name, '/')) {
                continue;
            }

            $section = ImportBundleLayout::sectionFor($name);

            if ($section !== null) {
                $entries[$section] = $name;
            }
        }

        return $entries;
    }

    /**
     * @param  resource  $stream
     * @param  array<string, string>  $studentInstructors  student_ref => instructor_ref, filled from students.csv
     * @return array{counts: array{records: int, staged: int}, errors: array<int, array{file: string, line: int, message: string}>}
     */
    protected function stageFile(ImportRun $run, string $section, $stream, array &$studentInstructors): array
    {
        $fileName = ImportBundleLayout::FILES[$section];
        $refColumn = ImportBundleLayout::REF_COLUMNS[$section];
        $headers = null;
        $line = 0;
        $records = 0;
        $staged = 0;
        $batch = [];

        while (($record = fgetcsv($stream, escape: '')) !== false) {
            $line++;

            if ($headers === null) {
                $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($record[0] ?? '')) ?? '';
                $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $record);
                $missing = array_diff(ImportBundleLayout::REQUIRED_COLUMNS[$section], $headers);

                if ($missing !== []) {
                    return [
                        'counts' => ['records' => 0, 'staged' => 0],
                        'errors' => [[
                            'file' => $fileName,
                            'line' => 1,
                            'message' => 'Missing column(s): '.implode(', ', $missing).'. The file was not read.',
                        ]],
                    ];
                }

                continue;
            }

            $records++;
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

            $studentRef = $row['student_ref'] ?? '';

            if ($section === 'students' && $studentRef !== '') {
                $studentInstructors[$studentRef] = $row['instructor_ref'] ?? '';
            }

            // A row with no instructor_ref of its own (e.g. many tests.csv rows) takes its pupil's instructor,
            // otherwise no stage job would ever pick it up.
            $ownInstructorRef = in_array($section, ImportBundleLayout::INSTRUCTOR_FROM_STUDENT, true) ? '' : ($row['instructor_ref'] ?? '');
            $instructorRef = $ownInstructorRef !== '' ? $ownInstructorRef : (($studentInstructors[$studentRef] ?? '') ?: null);

            $batch[] = [
                'import_run_id' => $run->id,
                'file' => $section,
                'instructor_ref' => $instructorRef,
                'source_ref' => $refColumn !== null && ($row[$refColumn] ?? '') !== '' ? mb_substr($row[$refColumn], 0, 191) : null,
                'line' => $line,
                'row' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ];

            if (count($batch) >= self::INSERT_BATCH) {
                DB::table('import_staged_rows')->insert($batch);
                $staged += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('import_staged_rows')->insert($batch);
            $staged += count($batch);
        }

        return ['counts' => ['records' => $records, 'staged' => $staged], 'errors' => []];
    }
}
