<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Itsa\FinalDeclaration;

use App\Enums\ItsaSupplementaryType;
use App\Support\HmrcMoney;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Validates one supplementary submission per request. The exact field set
 * depends on the route's `{type}` parameter (resolved through ItsaSupplementaryType).
 * Pence-conversion is performed in `prepareForValidation()` so the rule
 * set is uniform integer/min/max checks.
 */
class SubmitSupplementaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->instructor !== null;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->resolveType();
        if ($type === null) {
            return;
        }

        $merge = [];
        foreach ($type->v1Fields() as $field) {
            if (! str_ends_with($field, '_pence')) {
                continue;
            }
            $rawKey = substr($field, 0, -strlen('_pence'));
            $rawValue = $this->input($rawKey);
            if ($rawValue === null) {
                continue;
            }
            $merge[$field] = $this->safePence($rawValue);
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = $this->resolveType();
        if ($type === null) {
            return [];
        }

        return match ($type) {
            ItsaSupplementaryType::Reliefs => [
                'pension_contributions_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
                'one_off_pension_contributions_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
                'charitable_giving_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            ],
            ItsaSupplementaryType::Disclosures => [
                'marriage_allowance_recipient_nino' => ['nullable', 'string', 'max:32'],
                'marriage_allowance_start_date' => ['nullable', 'date_format:Y-m-d'],
            ],
            ItsaSupplementaryType::Savings => [
                'uk_interest_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
                'foreign_interest_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            ],
            ItsaSupplementaryType::Dividends => [
                'uk_dividends_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
                'other_uk_dividends_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            ],
            ItsaSupplementaryType::IndividualDetails => [
                'first_name' => ['required', 'string', 'max:80'],
                'last_name' => ['required', 'string', 'max:80'],
                'address_line_1' => ['required', 'string', 'max:120'],
                'address_line_2' => ['nullable', 'string', 'max:120'],
                'postcode' => ['required', 'string', 'max:16'],
                'marital_status' => ['required', 'string', 'in:single,married,civil_partnership,divorced,widowed'],
            ],
        };
    }

    public function resolveType(): ?ItsaSupplementaryType
    {
        $value = $this->route('type');
        if (! is_string($value)) {
            return null;
        }

        return ItsaSupplementaryType::tryFrom($value);
    }

    private function safePence(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return HmrcMoney::fromInput(is_string($value) ? $value : (float) $value);
        } catch (InvalidArgumentException) {
            return -1;
        }
    }
}
