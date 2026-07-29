<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateHazardPerceptionVideoRequest extends StoreHazardPerceptionVideoRequest
{
    /**
     * Same rules as store, except the video file is optional — omitting it
     * keeps the existing upload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['video'] = ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:512000'];

        return $rules;
    }
}
