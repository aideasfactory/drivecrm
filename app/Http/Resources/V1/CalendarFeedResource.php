<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{url: string, webcal_url: string, google_url: string} $resource
 */
class CalendarFeedResource extends JsonResource
{
    /**
     * @return array{url: string, webcal_url: string, google_url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource['url'],
            'webcal_url' => $this->resource['webcal_url'],
            'google_url' => $this->resource['google_url'],
        ];
    }
}
