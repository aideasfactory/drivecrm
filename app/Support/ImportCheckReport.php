<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Collects what a check of an import zip found, file by file:
 * - errors block the import (structure, duplicate or unknown refs);
 * - skipped rows are left out, counted by reason (with a few example lines);
 * - notes count rows the import will treat in a particular way
 *   (e.g. "placeholder email", "future fail → booked").
 * Kept small enough to store on the import run.
 */
class ImportCheckReport
{
    public const MAX_ERRORS = 200;

    public const MAX_EXAMPLES = 10;

    /** @var array<string, array{rows: int, already_imported: int, new: int, skipped: array<string, int>, notes: array<string, int>}> */
    protected array $files = [];

    /** @var array<int, array{file: string, line: int, message: string}> */
    protected array $errors = [];

    protected int $errorCount = 0;

    /** @var array<string, array<string, array<int, array{line: int, ref: string}>>> */
    protected array $examples = [];

    public function row(string $section): void
    {
        $this->count($section, 'rows');
    }

    public function alreadyImported(string $section): void
    {
        $this->count($section, 'already_imported');
    }

    public function new(string $section): void
    {
        $this->count($section, 'new');
    }

    public function skip(string $section, string $reason, int $line, string $ref = ''): void
    {
        $file = &$this->file($section);
        $file['skipped'][$reason] = ($file['skipped'][$reason] ?? 0) + 1;

        if (count($this->examples[$section][$reason] ?? []) < self::MAX_EXAMPLES) {
            $this->examples[$section][$reason][] = ['line' => $line, 'ref' => $ref];
        }
    }

    public function note(string $section, string $label, int $count = 1): void
    {
        $file = &$this->file($section);
        $file['notes'][$label] = ($file['notes'][$label] ?? 0) + $count;
    }

    public function error(string $fileName, int $line, string $message): void
    {
        $this->errorCount++;

        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['file' => $fileName, 'line' => $line, 'message' => $message];
        }
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    /**
     * @return array{files: array<string, array{rows: int, already_imported: int, new: int, skipped: array<string, int>, notes: array<string, int>}>, errors: array<int, array{file: string, line: int, message: string}>, error_count: int, examples: array<string, array<string, array<int, array{line: int, ref: string}>>>}
     */
    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'errors' => $this->errors,
            'error_count' => $this->errorCount,
            'examples' => $this->examples,
        ];
    }

    protected function count(string $section, string $key): void
    {
        $file = &$this->file($section);
        $file[$key]++;
    }

    /**
     * @return array{rows: int, already_imported: int, new: int, skipped: array<string, int>, notes: array<string, int>}
     */
    protected function &file(string $section): array
    {
        $this->files[$section] ??= ['rows' => 0, 'already_imported' => 0, 'new' => 0, 'skipped' => [], 'notes' => []];

        return $this->files[$section];
    }
}
