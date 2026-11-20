<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an instructor's request to permanently delete a past lesson stands.
 * Only one request per lesson can be pending at a time.
 */
enum LessonDeletionRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }
}
