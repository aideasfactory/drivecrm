<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\FinanceReceiptUploads;
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
        return [
            'receipt' => FinanceReceiptUploads::rules(required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return FinanceReceiptUploads::messages();
    }
}
