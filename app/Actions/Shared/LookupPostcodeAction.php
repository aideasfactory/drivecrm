<?php

declare(strict_types=1);

namespace App\Actions\Shared;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LookupPostcodeAction
{
    /**
     * Look up a UK postcode via postcodes.io and return coordinates.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function __invoke(string $postcode): ?array
    {
        $postcode = trim(strtoupper(str_replace(' ', '', $postcode)));

        if (empty($postcode)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get("https://api.postcodes.io/postcodes/{$postcode}");

            if ($response->successful() && $response->json('status') === 200) {
                $result = $response->json('result');

                return [
                    'latitude' => (float) $result['latitude'],
                    'longitude' => (float) $result['longitude'],
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Postcode lookup failed', [
                'postcode' => $postcode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
