<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchPostcodeCoordinatesAction
{
    /**
     * Fetch coordinates for a given UK postcode from postcodes.io API.
     *
     * @return array|null Returns ['latitude' => float, 'longitude' => float] or null if failed
     */
    public function __invoke(string $postcode): ?array
    {
        try {
            // Remove spaces and uppercase the postcode for API call
            $cleanPostcode = strtoupper(str_replace(' ', '', $postcode));

            // Call postcodes.io API
            $response = Http::timeout(10)
                ->get("https://api.postcodes.io/postcodes/{$cleanPostcode}");

            // Check if request was successful
            if ($response->successful() && $response->json('status') === 200) {
                $result = $response->json('result');

                return [
                    'latitude' => $result['latitude'] ?? null,
                    'longitude' => $result['longitude'] ?? null,
                ];
            }

            // Log the failure for debugging
            Log::warning('Postcode lookup failed', [
                'postcode' => $postcode,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;

        } catch (\Exception $e) {
            // Log any exceptions
            Log::error('Postcode lookup exception', [
                'postcode' => $postcode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
