<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Models\Instructor;
use App\Models\InstructorFinance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewInsuranceSplitAction
{
    private const ALLOWED_TARGETS = ['vehicle_insurance', 'business_insurance'];

    /**
     * Apply the instructor's re-tagging decisions to legacy `insurance` rows.
     *
     * Decisions array shape:
     * [
     *   ['finance_row_id' => 12, 'target_category' => 'vehicle_insurance'],
     *   ['finance_row_id' => 17, 'target_category' => 'business_insurance'],
     * ]
     *
     * Skipped rows (not in the decisions list) are intentionally left as
     * `insurance` and remain excluded from HMRC payloads until reviewed.
     *
     * @param  array<int, array{finance_row_id: int, target_category: string}>  $decisions
     * @return int Number of rows updated
     */
    public function __invoke(Instructor $instructor, array $decisions): int
    {
        $updated = 0;

        DB::transaction(function () use ($instructor, $decisions, &$updated): void {
            foreach ($decisions as $decision) {
                $target = $decision['target_category'] ?? null;
                $rowId = $decision['finance_row_id'] ?? null;

                if (! is_int($rowId) || ! in_array($target, self::ALLOWED_TARGETS, true)) {
                    throw new InvalidArgumentException(
                        'Each decision must include finance_row_id (int) and target_category (vehicle_insurance|business_insurance).',
                    );
                }

                $rowsAffected = InstructorFinance::query()
                    ->where('id', $rowId)
                    ->where('instructor_id', $instructor->id)
                    ->where('category', 'insurance')
                    ->update(['category' => $target]);

                $updated += $rowsAffected;
            }
        });

        return $updated;
    }
}
