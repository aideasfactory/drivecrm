<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Archive;

use Illuminate\Foundation\Http\FormRequest;

class StoreYearEndArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->instructor !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_year_start' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }
}
