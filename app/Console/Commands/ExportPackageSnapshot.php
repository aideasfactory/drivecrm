<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PackageRestoreService;
use Illuminate\Console\Command;

class ExportPackageSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:export-snapshot
        {package : The package id to export}
        {--path= : Where to write the encrypted file (default storage/app/private/package-{id}-snapshot.enc)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run on a backup: export a package and everything a delete cascades to (orders, lessons, payments, payouts, diary slots) into an encrypted file for packages:restore-snapshot. Read-only.';

    public function handle(PackageRestoreService $packageRestoreService): int
    {
        $packageId = (int) $this->argument('package');
        $path = (string) ($this->option('path') ?: storage_path("app/private/package-{$packageId}-snapshot.enc"));

        $result = $packageRestoreService->exportToFile($packageId, $path);

        $this->table(['Table', 'Rows'], collect($result['counts'])
            ->map(fn (int $rows, string $table): array => [$table, $rows])
            ->push(['students (guarantee links)', $result['guarantee_links']])
            ->values()
            ->all());

        $this->line("File: {$result['path']}");
        $this->newLine();
        $this->warn('Key (keep it out of the upload location; you need it to restore):');
        $this->line($result['key']);

        return self::SUCCESS;
    }
}
