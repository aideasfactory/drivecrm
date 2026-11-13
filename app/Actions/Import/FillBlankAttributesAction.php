<?php

declare(strict_types=1);

namespace App\Actions\Import;

use Illuminate\Database\Eloquent\Model;

class FillBlankAttributesAction
{
    /**
     * Set each value only where the model's own value is empty (null or ''),
     * and only when the new value isn't empty. A value already in Drive is
     * never changed. Saves once if anything was filled.
     *
     * @param  array<string, mixed>  $values  attribute => value from the import
     * @return array<int, string> The attributes that were filled
     */
    public function __invoke(Model $model, array $values): array
    {
        $filled = [];

        foreach ($values as $attribute => $value) {
            $current = $model->getAttribute($attribute);

            if (($current !== null && $current !== '') || $value === null || $value === '') {
                continue;
            }

            $model->setAttribute($attribute, $value);
            $filled[] = $attribute;
        }

        if ($filled !== []) {
            $model->save();
        }

        return $filled;
    }
}
