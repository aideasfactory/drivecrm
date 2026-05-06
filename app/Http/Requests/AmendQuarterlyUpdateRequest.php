<?php

declare(strict_types=1);

namespace App\Http\Requests;

class AmendQuarterlyUpdateRequest extends SubmitQuarterlyUpdateRequest
{
    /**
     * Amendments don't take period dates — those are pinned to the original row.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['period_start_date'], $rules['period_end_date']);

        return $rules;
    }
}
