<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Models\InstructorFinance;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class AttachImportedReceiptAction
{
    protected const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * Copy a receipt out of the import zip onto the same S3 location a manual
     * upload uses (`UploadFinanceReceiptAction`) and attach it to the finance
     * row, so it shows and downloads exactly like any other receipt.
     *
     * @param  string  $entryName  The file's full path inside the zip
     *
     * @throws RuntimeException When the entry cannot be read from the zip
     */
    public function __invoke(InstructorFinance $finance, ZipArchive $zip, string $entryName): void
    {
        $contents = $zip->getFromName($entryName);

        if ($contents === false) {
            throw new RuntimeException("Could not read receipt {$entryName} from the zip.");
        }

        $originalName = basename($entryName);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = "instructors/{$finance->instructor_id}/finance-receipts/{$finance->id}/".Str::random(40).".{$extension}";

        Storage::disk('s3')->put($path, $contents);

        $finance->update([
            'receipt_path' => $path,
            'receipt_original_name' => $originalName,
            'receipt_mime_type' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
            'receipt_size_bytes' => strlen($contents),
        ]);
    }
}
