<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BirdConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SyncBirdConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bird:sync {--full : Walk every Bird conversation instead of only recent activity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mirror Bird AI Employee conversations and messages into the local database';

    /**
     * Execute the console command.
     */
    public function handle(BirdConversationService $birdConversationService): int
    {
        try {
            $result = $birdConversationService->sync((bool) $this->option('full'));
        } catch (RuntimeException $exception) {
            Log::error('Bird conversation sync failed', ['error' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Synced %d conversations (%d messages).',
            $result['conversations'],
            $result['messages'],
        ));

        return self::SUCCESS;
    }
}
