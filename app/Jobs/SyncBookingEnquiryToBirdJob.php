<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Enquiry;
use App\Services\BirdContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBookingEnquiryToBirdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Enquiry $enquiry) {}

    /**
     * Queue a sync without ever throwing. Used from the booking flow and the
     * payment confirmation, which must not fail because Bird or the queue is
     * unavailable. Queued once any open database transaction commits, so the
     * job reads the saved data and a queue error can't surface mid-commit.
     */
    public static function dispatchSafely(Enquiry $enquiry): void
    {
        DB::afterCommit(function () use ($enquiry): void {
            try {
                app(Dispatcher::class)->dispatch(new self($enquiry));
            } catch (Throwable $exception) {
                Log::error('Could not queue Bird sync for enquiry', [
                    'enquiry_id' => $enquiry->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function handle(BirdContactService $bird): void
    {
        Log::info('SyncBookingEnquiryToBirdJob handling', [
            'enquiry_id' => $this->enquiry->id,
            'attempt' => $this->attempts(),
        ]);

        $bird->createFromEnquiry($this->enquiry);

        Log::info('SyncBookingEnquiryToBirdJob succeeded', [
            'enquiry_id' => $this->enquiry->id,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SyncBookingEnquiryToBirdJob failed after all retries', [
            'enquiry_id' => $this->enquiry->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
