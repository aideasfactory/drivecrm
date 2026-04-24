<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\InstructorFinance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFinanceReceiptAction
{
    /**
     * Upload a receipt to the private S3 disk and attach it to a finance record.
     * Replaces any existing receipt on the same record.
     */
    public function __invoke(InstructorFinance $finance, UploadedFile $file): InstructorFinance
    {
        if ($finance->receipt_path) {
            Storage::disk('s3')->delete($finance->receipt_path);
        }

        $directory = "instructors/{$finance->instructor_id}/finance-receipts/{$finance->id}";
        $path = $file->store($directory, 's3');

        $finance->update([
            'receipt_path' => $path,
            'receipt_original_name' => $file->getClientOriginalName(),
            'receipt_mime_type' => $file->getMimeType(),
            'receipt_size_bytes' => $file->getSize(),
        ]);

        return $finance->fresh();
    }
}
