<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHmrcFingerprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'screens' => ['required', 'array', 'min:1', 'max:8'],
            'screens.*.width' => ['required', 'integer', 'min:1', 'max:32768'],
            'screens.*.height' => ['required', 'integer', 'min:1', 'max:32768'],
            'screens.*.scaling_factor' => ['required', 'numeric', 'min:0.5', 'max:8'],
            'screens.*.colour_depth' => ['required', 'integer', 'min:1', 'max:64'],

            'window_size' => ['required', 'array'],
            'window_size.width' => ['required', 'integer', 'min:1', 'max:32768'],
            'window_size.height' => ['required', 'integer', 'min:1', 'max:32768'],

            'timezone' => ['required', 'array'],
            'timezone.iana' => ['required', 'string', 'max:64'],
            'timezone.offset_minutes' => ['required', 'integer', 'min:-840', 'max:840'],

            'browser_user_agent' => ['required', 'string', 'max:1024'],
        ];
    }
}
