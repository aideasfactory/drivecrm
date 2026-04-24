<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\InstructorFinance;
use Illuminate\Support\Facades\Storage;

class DeleteFinanceReceiptAction
{
    /**
     * Remove a receipt from S3 and clear the related DB columns.
     */
    public function __invoke(InstructorFinance $finance): InstructorFinance
    {
        if ($finance->receipt_path) {
            Storage::disk('s3')->delete($finance->receipt_path);
        }

        $finance->update([
            'receipt_path' => null,
            'receipt_original_name' => null,
            'receipt_mime_type' => null,
            'receipt_size_bytes' => null,
        ]);

        return $finance->fresh();
    }
}
