<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Support\ImportBundleLayout;
use RuntimeException;
use ZipArchive;

class VerifyImportManifestAction
{
    /**
     * Check the zip against its manifest.csv (file, row_count, sha256): every
     * listed file must be in the zip with exactly that many rows and that
     * checksum — proof the export arrived whole. A zip without a manifest
     * (the original 5-file bundle) passes with `present = false`.
     *
     * @param  array<string, array{entry: string, records: int, staged: int}>  $stagedFiles  From StageImportBundleAction (row counts)
     * @return array{present: bool, ok: bool, problems: array<int, string>}
     *
     * @throws RuntimeException When the zip can't be opened
     */
    public function __invoke(string $zipPath, array $stagedFiles): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the zip file.');
        }

        try {
            $manifestEntry = $this->findEntry($zip, ImportBundleLayout::MANIFEST);

            if ($manifestEntry === null) {
                return ['present' => false, 'ok' => true, 'problems' => []];
            }

            $problems = [];

            foreach ($this->readManifest($zip, $manifestEntry) as $fileName => $expected) {
                $section = ImportBundleLayout::sectionFor($fileName);
                $entry = $this->findEntry($zip, $fileName);

                if ($entry === null) {
                    $problems[] = "{$fileName} is in the manifest but not in the zip.";

                    continue;
                }

                if ($section !== null && isset($stagedFiles[$section]) && $stagedFiles[$section]['records'] !== $expected['row_count']) {
                    $problems[] = sprintf('%s has %d rows; the manifest says %d.', $fileName, $stagedFiles[$section]['records'], $expected['row_count']);
                }

                if ($expected['sha256'] !== '' && $this->sha256($zip, $entry) !== $expected['sha256']) {
                    $problems[] = "{$fileName} doesn't match its checksum in the manifest (the file changed or arrived incomplete).";
                }
            }

            return ['present' => true, 'ok' => $problems === [], 'problems' => $problems];
        } finally {
            $zip->close();
        }
    }

    protected function findEntry(ZipArchive $zip, string $fileName): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (! str_starts_with($name, '__MACOSX/') && strtolower(basename($name)) === strtolower($fileName)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{row_count: int, sha256: string}> file name (lower-cased) => expected values
     */
    protected function readManifest(ZipArchive $zip, string $entry): array
    {
        $stream = $zip->getStream($entry);

        if ($stream === false) {
            throw new RuntimeException('Could not read manifest.csv from the zip.');
        }

        $files = [];
        $headers = null;

        try {
            while (($record = fgetcsv($stream, escape: '')) !== false) {
                if ($headers === null) {
                    $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($record[0] ?? '')) ?? '';
                    $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $record);

                    continue;
                }

                $values = array_slice(array_map(fn ($value) => trim((string) $value), $record), 0, count($headers));
                $row = array_combine($headers, array_pad($values, count($headers), ''));
                $file = strtolower((string) ($row['file'] ?? ''));

                if ($file !== '') {
                    $files[$file] = [
                        'row_count' => (int) ($row['row_count'] ?? 0),
                        'sha256' => strtolower((string) ($row['sha256'] ?? '')),
                    ];
                }
            }
        } finally {
            fclose($stream);
        }

        return $files;
    }

    protected function sha256(ZipArchive $zip, string $entry): string
    {
        $stream = $zip->getStream($entry);

        if ($stream === false) {
            throw new RuntimeException("Could not read {$entry} from the zip.");
        }

        $context = hash_init('sha256');

        try {
            while (! feof($stream)) {
                hash_update($context, (string) fread($stream, 1048576));
            }
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }
}
