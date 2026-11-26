<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payout;
use App\Services\PayoutLabelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Labels the payment a lesson payout created in the instructor's Stripe
 * account ("Jane Smith, lesson 12 Nov 2026 (Booking #123)"). Purely cosmetic:
 * it runs after the payout is committed and can never affect it.
 */
class LabelInstructorPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Payout $payout,
        public ?string $destinationPaymentId = null,
    ) {}

    /**
     * Queue the label without ever throwing, once the sign-off transaction
     * has committed (a rolled-back sign-off queues nothing).
     */
    public static function dispatchSafely(Payout $payout, ?string $destinationPaymentId = null): void
    {
        DB::afterCommit(function () use ($payout, $destinationPaymentId): void {
            try {
                app(Dispatcher::class)->dispatch(new self($payout, $destinationPaymentId));
            } catch (Throwable $exception) {
                Log::error('Could not queue instructor payout label', [
                    'payout_id' => $payout->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function handle(PayoutLabelService $payoutLabelService): void
    {
        if (! $payoutLabelService->label($this->payout, $this->destinationPaymentId)) {
            // Retried by the queue (Stripe may not have the payment ready yet).
            throw new RuntimeException('Instructor payout label not written yet');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('LabelInstructorPayoutJob gave up: payout is fine, only its Stripe label is missing', [
            'payout_id' => $this->payout->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
