<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Package\ExportPackageSnapshotAction;
use App\Actions\Package\RestorePackageSnapshotAction;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Moves a hard-deleted package between databases: export a snapshot from a backup into an
 * encrypted file, then restore it elsewhere from a URL or path. The file is gzipped JSON
 * encrypted with a one-off key, so it can be hosted anywhere; it is useless without the key.
 */
class PackageRestoreService extends BaseService
{
    private const CIPHER = 'aes-256-cbc';

    public function __construct(
        protected ExportPackageSnapshotAction $exportSnapshot,
        protected RestorePackageSnapshotAction $restoreSnapshot,
    ) {}

    /**
     * @return array{path: string, key: string, counts: array<string, int>, guarantee_links: int}
     */
    public function exportToFile(int $packageId, string $path): array
    {
        $snapshot = ($this->exportSnapshot)($packageId);
        $key = Encrypter::generateKey(self::CIPHER);

        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $payload = (new Encrypter($key, self::CIPHER))->encryptString(gzencode($json, 9));

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $payload);

        return [
            'path' => $path,
            'key' => base64_encode($key),
            'counts' => array_map('count', $snapshot['tables']),
            'guarantee_links' => count($snapshot['guarantee_links']),
        ];
    }

    /**
     * @return array{
     *     package_id: int,
     *     exported_at: string,
     *     source_database: string,
     *     report: array<string, mixed>
     * }
     */
    public function restoreFromSource(string $source, string $key, bool $dryRun): array
    {
        $snapshot = $this->readSnapshot($source, $key);

        return [
            'package_id' => (int) $snapshot['package_id'],
            'exported_at' => (string) $snapshot['exported_at'],
            'source_database' => (string) $snapshot['source_database'],
            'report' => ($this->restoreSnapshot)($snapshot, $dryRun),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readSnapshot(string $source, string $key): array
    {
        $payload = str_starts_with($source, 'http://') || str_starts_with($source, 'https://')
            ? Http::timeout(120)->get($source)->throw()->body()
            : (is_file($source) ? file_get_contents($source) : throw new RuntimeException("File not found: {$source}"));

        $rawKey = base64_decode($key, true);

        if ($rawKey === false || ! Encrypter::supported($rawKey, self::CIPHER)) {
            throw new RuntimeException('The key is not valid. Use the exact key printed by packages:export-snapshot.');
        }

        $json = gzdecode((new Encrypter($rawKey, self::CIPHER))->decryptString(trim((string) $payload)));

        if ($json === false) {
            throw new RuntimeException('Snapshot could not be unzipped.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
