<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Vehicles;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');
        $instructor = $this->user()?->instructor;

        return $vehicle instanceof Vehicle
            && $instructor !== null
            && $vehicle->instructor_id === $instructor->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:100'],
            'registration' => ['sometimes', 'nullable', 'string', 'max:16'],
            'engine_size_cc' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'business_use_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'acquired_on' => ['sometimes', 'required', 'date'],
        ];
    }
}
