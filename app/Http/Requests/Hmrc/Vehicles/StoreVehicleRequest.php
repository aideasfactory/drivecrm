<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Vehicles;

use App\Enums\VehicleMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreVehicleRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:100'],
            'registration' => ['nullable', 'string', 'max:16'],
            'engine_size_cc' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'method' => ['nullable', new Enum(VehicleMethod::class)],
            'business_use_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'acquired_on' => ['required', 'date'],
        ];
    }
}
