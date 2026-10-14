<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Enquiry;
use App\Models\Order;
use App\Services\BirdContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes an onboarding order's booking and payment state to the learner's
 * Bird contact. Dispatched when the unpaid order is created and again when
 * its first payment confirms; the order is re-read when the job runs, so
 * whichever sync runs last reflects the current payment state.
 */
class SyncOnboardingBookingToBirdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Order $order) {}

    public function handle(BirdContactService $bird): void
    {
        $enquiry = Enquiry::query()
            ->where('data->steps->step6->order_id', $this->order->id)
            ->latest()
            ->first();

        if (! $enquiry) {
            Log::info('SyncOnboardingBookingToBirdJob skipped: order did not come from onboarding', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        Log::info('SyncOnboardingBookingToBirdJob handling', [
            'order_id' => $this->order->id,
            'enquiry_id' => $enquiry->id,
            'attempt' => $this->attempts(),
        ]);

        $bird->syncOnboardingBooking($enquiry, $this->order);

        Log::info('SyncOnboardingBookingToBirdJob succeeded', [
            'order_id' => $this->order->id,
            'enquiry_id' => $enquiry->id,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SyncOnboardingBookingToBirdJob failed after all retries', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
