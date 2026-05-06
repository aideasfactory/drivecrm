<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Itsa\FinalDeclaration;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFinalDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->instructor !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'attestation' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attestation.accepted' => 'You must confirm the digital-records attestation before submitting the final declaration.',
        ];
    }
}
