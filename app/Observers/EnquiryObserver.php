<?php

namespace App\Observers;

use App\Models\Enquiry;
use Illuminate\Support\Facades\Log;

class EnquiryObserver
{
    /**
     * Handle the Enquiry "created" event.
     */
    public function created(Enquiry $enquiry): void
    {
        $this->notifyExternalSystem($enquiry, 'created');
    }

    /**
     * Handle the Enquiry "updated" event.
     */
    public function updated(Enquiry $enquiry): void
    {
        $this->notifyExternalSystem($enquiry, 'updated');
    }

    /**
     * Send notification to external marketing system
     * Implementation deferred — currently logs only
     */
    private function notifyExternalSystem(Enquiry $enquiry, string $event): void
    {
        $payload = [
            'enquiry_uuid' => $enquiry->id,
            'current_step' => $enquiry->current_step,
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
        ];

        // TODO: Replace with actual HTTP call to marketing system
        Log::channel('enquiry_events')->info('Enquiry event', $payload);

        // Future implementation:
        // Http::post(config('services.marketing.endpoint'), $payload);
    }
}
