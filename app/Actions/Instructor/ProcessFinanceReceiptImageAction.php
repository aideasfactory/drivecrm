<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\ReceiptStatus;
use App\Models\InstructorFinance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

class ProcessFinanceReceiptImageAction
{
    /** Image types that get shrunk; everything else (PDFs) is stored as uploaded. */
    public const PROCESSABLE_MIME_TYPES = ['image/jpeg', 'image/png'];

    public static function isProcessable(?string $mimeType): bool
    {
        return in_array($mimeType, self::PROCESSABLE_MIME_TYPES, true);
    }

    /**
     * Shrink an uploaded receipt photo and create its list thumbnail.
     *
     * 1. Skip unless the record still has this exact file and is `processing`
     *    (the instructor may have replaced or removed the receipt since upload).
     * 2. Decode with EXIF auto-orientation (Android stores portrait photos sideways
     *    with a "rotate" tag), scale down to the configured long edge, re-encode as
     *    JPEG with metadata stripped, then scale again for the thumbnail.
     * 3. Store both new files, then swap them in under a row lock — re-checking the
     *    guard, so a receipt replaced mid-processing is never overwritten.
     * 4. Only after the record points at the new files, delete the original.
     *
     * Throws on read/decode/store failure so the job can retry; the job's `failed()`
     * keeps the original and marks it ready via MarkFinanceReceiptReadyAction.
     *
     * @return bool True when the shrunk copy was swapped in; false when skipped
     */
    public function __invoke(int $financeId, string $sourcePath): bool
    {
        if (! $this->isStillCurrent(InstructorFinance::find($financeId), $sourcePath)) {
            return false;
        }

        $disk = Storage::disk('s3');
        $contents = $disk->get($sourcePath);

        if ($contents === null) {
            throw new RuntimeException("Receipt file {$sourcePath} could not be read for finance record {$financeId}.");
        }

        $settings = config('finances.receipt.processing');

        $image = $this->imageManager()->decodeBinary($contents);
        unset($contents);

        $image->scaleDown((int) $settings['max_edge_px'], (int) $settings['max_edge_px']);
        $receipt = $image->encode(new JpegEncoder(quality: (int) $settings['jpeg_quality'], strip: true));

        $image->scaleDown((int) $settings['thumbnail_edge_px'], (int) $settings['thumbnail_edge_px']);
        $thumbnail = $image->encode(new JpegEncoder(quality: (int) $settings['thumbnail_jpeg_quality'], strip: true));

        unset($image);

        $basePath = dirname($sourcePath).'/'.Str::uuid()->toString();
        $receiptPath = "{$basePath}.jpg";
        $thumbnailPath = "{$basePath}-thumb.jpg";

        if (! $disk->put($receiptPath, $receipt->toString()) || ! $disk->put($thumbnailPath, $thumbnail->toString())) {
            $disk->delete([$receiptPath, $thumbnailPath]);

            throw new RuntimeException("Processed receipt for finance record {$financeId} could not be stored.");
        }

        $previousThumbnailPath = DB::transaction(function () use ($financeId, $sourcePath, $receiptPath, $thumbnailPath, $receipt): string|false|null {
            $finance = InstructorFinance::query()->lockForUpdate()->find($financeId);

            if (! $this->isStillCurrent($finance, $sourcePath)) {
                return false;
            }

            $previousThumbnailPath = $finance->receipt_thumbnail_path;

            $finance->update([
                'receipt_path' => $receiptPath,
                'receipt_original_name' => $this->jpegName($finance->receipt_original_name),
                'receipt_mime_type' => 'image/jpeg',
                'receipt_size_bytes' => $receipt->size(),
                'receipt_thumbnail_path' => $thumbnailPath,
                'receipt_status' => ReceiptStatus::Ready,
            ]);

            return $previousThumbnailPath;
        });

        if ($previousThumbnailPath === false) {
            $disk->delete([$receiptPath, $thumbnailPath]);

            return false;
        }

        $disk->delete(array_values(array_filter([$sourcePath, $previousThumbnailPath])));

        return true;
    }

    private function isStillCurrent(?InstructorFinance $finance, string $sourcePath): bool
    {
        return $finance !== null
            && $finance->receipt_path === $sourcePath
            && $finance->receipt_status === ReceiptStatus::Processing;
    }

    /**
     * Imagick handles large camera photos with less PHP memory than GD; GD is the
     * fallback where the extension isn't installed. Auto-orientation is applied on
     * decode, so the image is upright before resizing.
     */
    private function imageManager(): ImageManager
    {
        $driver = extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class;

        return ImageManager::usingDriver($driver, autoOrientation: true, decodeAnimation: false, strip: true);
    }

    private function jpegName(?string $originalName): string
    {
        $base = pathinfo((string) $originalName, PATHINFO_FILENAME);

        return ($base !== '' ? $base : 'receipt').'.jpg';
    }
}
