<?php

declare(strict_types=1);

namespace App\Actions\Shared;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LookupPostcodesInBulkAction
{
    /** postcodes.io accepts at most this many postcodes per bulk request. */
    public const MAX_PER_REQUEST = 100;

    /**
     * Look up to 100 UK postcodes in one postcodes.io bulk request. Rate
     * limited (429) or server errors are retried with a growing pause; if it
     * still fails, a RuntimeException stops the run (a re-run carries on).
     *
     * @param  array<int, string>  $postcodes
     * @return array<string, array{latitude: float, longitude: float}|null> Keyed by the postcode as given; null = not found
     *
     * @throws RuntimeException When postcodes.io can't be reached after retries
     */
    public function __invoke(array $postcodes): array
    {
        $postcodes = array_values(array_unique($postcodes));

        if ($postcodes === []) {
            return [];
        }

        if (count($postcodes) > self::MAX_PER_REQUEST) {
            throw new RuntimeException('At most '.self::MAX_PER_REQUEST.' postcodes per request.');
        }

        try {
            $response = Http::timeout(30)
                ->retry(4, fn (int $attempt) => $attempt * 5000, fn ($exception) => $exception instanceof RequestException
                    && ($exception->response->status() === 429 || $exception->response->serverError()))
                ->post('https://api.postcodes.io/postcodes', ['postcodes' => $postcodes]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('postcodes.io lookup failed: '.$exception->getMessage(), 0, $exception);
        }

        $results = array_fill_keys($postcodes, null);

        foreach ((array) $response->json('result', []) as $item) {
            $query = $item['query'] ?? null;
            $result = $item['result'] ?? null;

            if ($query !== null && is_array($result) && isset($result['latitude'], $result['longitude'])) {
                $results[$query] = ['latitude' => (float) $result['latitude'], 'longitude' => (float) $result['longitude']];
            }
        }

        return $results;
    }
}
