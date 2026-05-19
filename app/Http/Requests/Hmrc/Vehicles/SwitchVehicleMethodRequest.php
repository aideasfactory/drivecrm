<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Vehicles;

use App\Enums\VehicleMethod;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SwitchVehicleMethodRequest extends FormRequest
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
            'method' => ['required', new Enum(VehicleMethod::class)],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }
}
