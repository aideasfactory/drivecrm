<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\InstructorFinance;
use Illuminate\Support\Facades\Storage;

class DeleteFinanceReceiptAction
{
    /**
     * Remove a receipt (and its thumbnail) from S3 and clear the related DB columns.
     * A shrink job still queued for it sees the cleared path and skips.
     */
    public function __invoke(InstructorFinance $finance): InstructorFinance
    {
        $paths = array_values(array_filter([$finance->receipt_path, $finance->receipt_thumbnail_path]));

        if ($paths !== []) {
            Storage::disk('s3')->delete($paths);
        }

        $finance->update([
            'receipt_path' => null,
            'receipt_original_name' => null,
            'receipt_mime_type' => null,
            'receipt_size_bytes' => null,
            'receipt_thumbnail_path' => null,
            'receipt_status' => null,
        ]);

        return $finance->fresh();
    }
}
