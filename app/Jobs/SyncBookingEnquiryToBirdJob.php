<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Enquiry;
use App\Services\BirdContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBookingEnquiryToBirdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Enquiry $enquiry) {}

    public function handle(BirdContactService $bird): void
    {
        $bird->createFromEnquiry($this->enquiry);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SyncBookingEnquiryToBirdJob failed after all retries', [
            'enquiry_id' => $this->enquiry->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
