<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinanceReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('finances.receipt.max_size_kb', 10240);
        $mimes = implode(',', config('finances.receipt.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']));

        return [
            'receipt' => ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }
}
