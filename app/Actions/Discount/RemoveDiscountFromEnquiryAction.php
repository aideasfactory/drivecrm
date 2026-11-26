<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\Enquiry;
use Illuminate\Support\Facades\Log;

class RemoveDiscountFromEnquiryAction
{
    /**
     * Take any discount (typed code or discount link) off an enquiry.
     */
    public function __invoke(Enquiry $enquiry): Enquiry
    {
        $data = $enquiry->data ?? [];

        if (! array_key_exists('discount', $data)) {
            return $enquiry;
        }

        Log::info('Discount removed from enquiry', [
            'enquiry_id' => $enquiry->id,
            'discount_code_id' => $data['discount']['id'] ?? null,
        ]);

        unset($data['discount']);
        $enquiry->data = $data;
        $enquiry->save();

        return $enquiry;
    }
}
