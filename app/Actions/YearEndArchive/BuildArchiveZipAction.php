<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class BuildArchiveZipAction
{
    /**
     * Recursively zip the contents of $stagingDir into the file at $zipPath.
     * Returns the resulting file size in bytes.
     */
    public function __invoke(string $stagingDir, string $zipPath): int
    {
        $dir = dirname($zipPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Could not create directory: {$dir}");
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not open zip for writing: {$zipPath}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stagingDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $stagingDirReal = (string) realpath($stagingDir);

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $absolute = $entry->getPathname();
            $relative = ltrim(substr($absolute, strlen($stagingDirReal)), DIRECTORY_SEPARATOR);

            if ($entry->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($absolute, $relative);
            }
        }

        $zip->close();

        $size = filesize($zipPath);

        return $size === false ? 0 : (int) $size;
    }
}
