<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\ReceiptStatus;
use App\Models\InstructorFinance;

class MarkFinanceReceiptReadyAction
{
    /**
     * Keep the original upload as the final receipt when it can't be shrunk
     * (processing failed for good, or the job couldn't be queued). The original
     * is a valid receipt, so the instructor never sees an error for it — there
     * is just no thumbnail.
     *
     * Only touches the record while it still holds this file in `processing`,
     * so a newer receipt is never affected.
     */
    public function __invoke(int $financeId, string $sourcePath): void
    {
        InstructorFinance::query()
            ->whereKey($financeId)
            ->where('receipt_path', $sourcePath)
            ->where('receipt_status', ReceiptStatus::Processing->value)
            ->update(['receipt_status' => ReceiptStatus::Ready->value]);
    }
}
