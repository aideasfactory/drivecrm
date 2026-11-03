<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Processing state of a finance receipt image.
 *
 * Images are stored as uploaded and shrunk in the background
 * (`ProcessFinanceReceiptImageJob`). PDFs, imported receipts and receipts
 * uploaded before processing existed have no stored status and count as ready.
 */
enum ReceiptStatus: string
{
    /** Original stored; shrinking and thumbnail still queued or running. */
    case Processing = 'processing';

    /** Final file in place (shrunk copy, or the original if shrinking failed). */
    case Ready = 'ready';
}
