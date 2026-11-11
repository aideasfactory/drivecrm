<?php

declare(strict_types=1);

namespace App\Actions\Import;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FetchRemoteImportFileAction
{
    /**
     * The file names listed in a manifest (one per line; blank lines and
     * lines starting with # ignored). Used when the files sit in a bucket
     * that can't be listed.
     *
     * @return array<int, string>
     *
     * @throws RuntimeException When the manifest can't be fetched
     */
    public function manifest(string $url): array
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Could not fetch the manifest {$url} (HTTP {$response->status()}).");
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $response->body())),
            fn (string $line) => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }

    /**
     * Whether a file exists at the URL (HEAD request).
     */
    public function exists(string $url): bool
    {
        try {
            return Http::timeout(15)->head($url)->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Download a file to a fresh temp folder, keeping its file name, and
     * return the local path. The caller deletes it (see cleanUp()).
     *
     * @throws RuntimeException When the download fails
     */
    public function download(string $url, string $fileName): string
    {
        $directory = sys_get_temp_dir().'/drivecrm-import-'.Str::random(16);

        if (! @mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create a temp folder for {$fileName}.");
        }

        $path = $directory.'/'.basename($fileName);
        $response = Http::timeout(60)->sink($path)->get($url);

        if (! $response->successful() || ! is_file($path) || filesize($path) === 0) {
            $this->cleanUp($path);

            throw new RuntimeException("Could not download {$fileName} (HTTP {$response->status()}).");
        }

        return $path;
    }

    /**
     * Delete a downloaded file and its temp folder.
     */
    public function cleanUp(string $path): void
    {
        @unlink($path);
        @rmdir(dirname($path));
    }
}
