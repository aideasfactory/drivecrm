<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lesson;

/**
 * What an instructor sees against each payout in their Stripe account:
 * "Jane Smith, lesson 12 Nov 2026 (Booking #123)". Text only, never money.
 *
 * The name is the learner's own (not the account holder's, who may be a
 * parent booking for them). Only the name, date and booking number go to
 * Stripe: no contact details.
 */
final class PayoutDescriptions
{
    /**
     * @return array{description: string, metadata: array<string, string>}
     */
    public static function forLesson(Lesson $lesson): array
    {
        $learnerName = self::learnerName($lesson);
        $bookingRef = $lesson->order_id !== null ? 'Booking #'.$lesson->order_id : null;
        $kind = $lesson->is_practical_test ? 'driving test' : 'lesson';
        $date = $lesson->date?->format('d M Y');

        $description = $learnerName.', '.trim($kind.' '.($date ?? ''));

        if ($bookingRef !== null) {
            $description .= ' ('.$bookingRef.')';
        }

        return [
            'description' => $description,
            'metadata' => array_filter([
                'learner_name' => $learnerName,
                'student_id' => self::idString($lesson->order?->student_id),
                'booking_ref' => $bookingRef,
                'order_id' => self::idString($lesson->order_id),
                'lesson_id' => self::idString($lesson->id),
                'lesson_date' => $lesson->date?->format('Y-m-d'),
            ], fn (?string $value): bool => $value !== null && $value !== ''),
        ];
    }

    protected static function learnerName(Lesson $lesson): string
    {
        $student = $lesson->order?->student;
        $name = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        return $name !== '' ? $name : 'Learner';
    }

    protected static function idString(int|string|null $id): ?string
    {
        return $id === null ? null : (string) $id;
    }
}
