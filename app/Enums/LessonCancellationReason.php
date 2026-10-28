<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a lesson was cancelled, picked by whoever cancels it. Each reason also
 * says who cancelled, so Head Office can decide whether the pupil is refunded.
 */
enum LessonCancellationReason: string
{
    case PupilRequest = 'pupil_request';
    case PupilUnwell = 'pupil_unwell';
    case PupilNoShow = 'pupil_no_show';
    case PupilStoppedLessons = 'pupil_stopped_lessons';
    case InstructorUnavailable = 'instructor_unavailable';
    case InstructorUnwell = 'instructor_unwell';
    case VehicleProblem = 'vehicle_problem';
    case Weather = 'weather';
    case Other = 'other';
    /** Set by `lessons:expire-banked`, never picked by a person. */
    case BankedExpired = 'banked_expired';

    public function label(): string
    {
        return match ($this) {
            self::PupilRequest => 'Pupil asked to cancel',
            self::PupilUnwell => 'Pupil unwell',
            self::PupilNoShow => "Pupil didn't turn up",
            self::PupilStoppedLessons => 'Pupil no longer needs lessons',
            self::InstructorUnavailable => 'Instructor unavailable',
            self::InstructorUnwell => 'Instructor unwell',
            self::VehicleProblem => 'Car problem',
            self::Weather => 'Weather / unsafe conditions',
            self::Other => 'Other',
            self::BankedExpired => 'Not rebooked in time',
        };
    }

    /**
     * Who the cancellation is down to: `pupil`, `instructor` or `other`.
     */
    public function cancelledBy(): string
    {
        return match ($this) {
            self::PupilRequest, self::PupilUnwell, self::PupilNoShow, self::PupilStoppedLessons => 'pupil',
            self::InstructorUnavailable, self::InstructorUnwell, self::VehicleProblem => 'instructor',
            self::Weather, self::Other, self::BankedExpired => 'other',
        };
    }

    public function cancelledByLabel(): string
    {
        return match ($this->cancelledBy()) {
            'pupil' => 'Pupil',
            'instructor' => 'Instructor',
            default => 'Other',
        };
    }

    /**
     * Whether free-text details must accompany this reason.
     */
    public function requiresDetails(): bool
    {
        return $this === self::Other;
    }

    /**
     * The label followed by any free-text details, for emails and notes.
     */
    public function describe(?string $details): string
    {
        $details = trim((string) $details);

        return $details === '' ? $this->label() : "{$this->label()}: {$details}";
    }

    /**
     * The reasons a person can pick (not the system-only `banked_expired`).
     *
     * @return array<int, array{value: string, label: string, cancelled_by: string, details_required: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'cancelled_by' => $case->cancelledBy(),
                'details_required' => $case->requiresDetails(),
            ],
            array_values(array_filter(self::cases(), fn (self $case): bool => $case !== self::BankedExpired)),
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
