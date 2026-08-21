<?php

declare(strict_types=1);

namespace App\Enums;

enum TransferReason: string
{
    case Part3Failed = 'part_3_failed';
    case DidntLikeInstructor = 'didnt_like_instructor';
    case NoAvailability = 'no_availability';
    case WrongTransmission = 'wrong_transmission';
    case Complaint = 'complaint';

    public function label(): string
    {
        return match ($this) {
            self::Part3Failed => 'Part 3 failed',
            self::DidntLikeInstructor => "Didn't like instructor",
            self::NoAvailability => 'No availability',
            self::WrongTransmission => 'Wrong transmission',
            self::Complaint => 'Complaint',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
