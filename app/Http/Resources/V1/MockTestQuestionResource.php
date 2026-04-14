<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MockTestQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageBaseUrl = config('mock_tests.image_base_url', '/storage/mock-test-images');

        return [
            'id' => $this->id,
            'stem' => $this->stem,
            'stem_image' => $this->stem_image ? "{$imageBaseUrl}/{$this->category}/{$this->stem_image}" : null,
            'option_a' => $this->option_a,
            'option_a_image' => $this->option_a_image ? "{$imageBaseUrl}/{$this->category}/{$this->option_a_image}" : null,
            'option_b' => $this->option_b,
            'option_b_image' => $this->option_b_image ? "{$imageBaseUrl}/{$this->category}/{$this->option_b_image}" : null,
            'option_c' => $this->option_c,
            'option_c_image' => $this->option_c_image ? "{$imageBaseUrl}/{$this->category}/{$this->option_c_image}" : null,
            'option_d' => $this->option_d,
            'option_d_image' => $this->option_d_image ? "{$imageBaseUrl}/{$this->category}/{$this->option_d_image}" : null,
            'topic' => $this->topic,
            'explanation' => $this->explanation,
        ];
    }
}
