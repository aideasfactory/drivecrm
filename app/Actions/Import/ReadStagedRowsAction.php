<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\ImportRun;
use Generator;
use Illuminate\Support\Facades\DB;

class ReadStagedRowsAction
{
    /**
     * One file's staged rows for one instructor (or every instructor when
     * $instructorRef is null), in file order, read in chunks.
     *
     * @return Generator<int, array{line: int, row: array<string, string>}>
     */
    public function __invoke(ImportRun $run, string $section, ?string $instructorRef): Generator
    {
        $query = DB::table('import_staged_rows')
            ->where('import_run_id', $run->id)
            ->where('file', $section)
            ->when($instructorRef !== null, fn ($query) => $query->where('instructor_ref', $instructorRef))
            ->select(['id', 'line', 'row']);

        foreach ($query->lazyById(1000) as $staged) {
            yield [
                'line' => (int) $staged->line,
                'row' => (array) json_decode((string) $staged->row, true),
            ];
        }
    }

    /**
     * All of them at once (for files with few rows per instructor).
     *
     * @return array<int, array{line: int, row: array<string, string>}>
     */
    public function all(ImportRun $run, string $section, ?string $instructorRef): array
    {
        return iterator_to_array(($this)($run, $section, $instructorRef), false);
    }
}
