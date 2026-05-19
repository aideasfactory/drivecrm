<?php

declare(strict_types=1);

namespace App\Console\Commands\Hmrc;

use App\Models\YearEndArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneYearEndArchivesCommand extends Command
{
    protected $signature = 'hmrc:prune-year-end-archives';

    protected $description = 'Delete year-end archive files older than HMRC retention. Keeps the DB row with status=expired so the UI can offer "Regenerate".';

    public function handle(): int
    {
        $disk = (string) config('hmrc.year_end_archive.disk', 'local');
        $now = Carbon::now();

        $expired = YearEndArchive::query()
            ->where('status', YearEndArchive::STATUS_READY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($expired as $archive) {
            if ($archive->file_path !== null && Storage::disk($disk)->exists($archive->file_path)) {
                try {
                    Storage::disk($disk)->delete($archive->file_path);
                } catch (\Throwable $e) {
                    Log::warning('Year-end archive prune: could not delete file', [
                        'archive_id' => $archive->id,
                        'file_path' => $archive->file_path,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            $archive->update([
                'status' => YearEndArchive::STATUS_EXPIRED,
                'file_path' => null,
                'file_size_bytes' => null,
                'purged_at' => $now,
            ]);

            $count++;
        }

        $this->info("Pruned {$count} expired archive(s).");

        return self::SUCCESS;
    }
}
