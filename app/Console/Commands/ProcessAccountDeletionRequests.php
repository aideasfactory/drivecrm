<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Services\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAccountDeletionRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account:process-deletion-requests {--dry-run : Show which requests would be processed without processing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hard-process pending account deletion requests whose 30-day grace period has elapsed';

    /**
     * Execute the console command.
     */
    public function handle(AccountDeletionService $accountDeletionService): int
    {
        $due = AccountDeletionRequest::query()->due()->with('user')->get();

        if ($this->option('dry-run')) {
            $this->info("Would process {$due->count()} due deletion request(s).");

            foreach ($due as $deletionRequest) {
                $this->line("  #{$deletionRequest->id} — user {$deletionRequest->user_id} — scheduled for {$deletionRequest->scheduled_for->toDateTimeString()}");
            }

            return Command::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($due as $deletionRequest) {
            try {
                $accountDeletionService->processDueRequest($deletionRequest);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;

                Log::error('Failed to process account deletion request', [
                    'deletion_request_id' => $deletionRequest->id,
                    'user_id' => $deletionRequest->user_id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to process deletion request #{$deletionRequest->id}: {$e->getMessage()}");
            }
        }

        Log::info('Account deletion request processing completed', [
            'processed' => $processed,
            'failed' => $failed,
        ]);

        $this->info("Processed {$processed} deletion request(s), {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
