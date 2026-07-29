<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHazardPerceptionVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', 'string', 'in:Car,ADI,Motorcycle,LGV-PCV'],
            'topic' => ['required', 'string', 'max:100'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'is_double_hazard' => ['required', 'boolean'],
            'has_recap' => ['required', 'boolean'],
            'video' => ['required', 'file', 'mimes:mp4,webm,mov', 'max:512000'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'recap_video' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:512000'],
            'zones' => ['required', 'array'],
            'zones.*.hazard_number' => ['required', 'integer', 'in:1,2'],
            'zones.*.score' => ['required', 'integer', 'between:1,5'],
            'zones.*.start_seconds' => ['required', 'numeric', 'min:0', 'max:9999'],
            'zones.*.end_seconds' => ['required', 'numeric', 'gt:zones.*.start_seconds', 'max:9999'],
        ];
    }

    /**
     * Each hazard must define exactly one zone per score (5, 4, 3, 2, 1).
     * Hazard 2 zones are required for double hazard clips and forbidden otherwise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $zoneErrors = collect($validator->errors()->keys())
                ->contains(fn (string $key): bool => $key === 'zones' || str_starts_with($key, 'zones.'));

            if ($zoneErrors || $validator->errors()->has('is_double_hazard')) {
                return;
            }

            $zones = collect($this->input('zones', []))
                ->map(fn (array $zone): array => [
                    'hazard_number' => (int) $zone['hazard_number'],
                    'score' => (int) $zone['score'],
                ]);
            $expectedHazards = $this->boolean('is_double_hazard') ? [1, 2] : [1];

            foreach ($expectedHazards as $hazardNumber) {
                $scores = $zones
                    ->where('hazard_number', $hazardNumber)
                    ->pluck('score')
                    ->sort()
                    ->values()
                    ->all();

                if ($scores !== [1, 2, 3, 4, 5]) {
                    $validator->errors()->add(
                        'zones',
                        "Hazard {$hazardNumber} must have exactly one scoring zone for each score from 5 to 1.",
                    );
                }
            }

            if (! $this->boolean('is_double_hazard') && $zones->where('hazard_number', 2)->isNotEmpty()) {
                $validator->errors()->add(
                    'zones',
                    'Hazard 2 zones are only allowed on double hazard clips.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video.required' => 'Please select a video file to upload.',
            'video.mimes' => 'The video must be an MP4, WebM, or MOV file.',
            'video.max' => 'The video must not be larger than 500MB.',
            'recap_video.mimes' => 'The recap video must be an MP4, WebM, or MOV file.',
            'recap_video.max' => 'The recap video must not be larger than 500MB.',
            'thumbnail.max' => 'The thumbnail must not be larger than 10MB.',
            'category.in' => 'The category must be Car, ADI, Motorcycle, or LGV-PCV.',
            'zones.required' => 'Scoring zones are required.',
            'zones.*.end_seconds.gt' => 'Each zone must end after it starts.',
        ];
    }
}
