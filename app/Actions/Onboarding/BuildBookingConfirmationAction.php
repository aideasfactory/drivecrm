<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\PdiStatus;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\Order;
use App\Support\TestPassGuarantee;

class BuildBookingConfirmationAction
{
    /**
     * Learner, instructor and lesson details for the onboarding thank-you page.
     * Instructor data is limited to what is safe to show the person booking.
     *
     * @return array{
     *     greeting_name: ?string,
     *     learner_first_name: ?string,
     *     booked_by_contact: bool,
     *     package_name: ?string,
     *     lessons_count: ?int,
     *     first_lesson: ?array{date: string, start_time: ?string},
     *     instructor: ?array{name: ?string, first_name: ?string, avatar: ?string, qualification: string, rating: mixed, reviews: mixed},
     *     guarantee: array{included: bool, terms_url: string},
     * }
     */
    public function __invoke(Enquiry $enquiry, ?Order $order): array
    {
        $step1 = $enquiry->getStepData(1) ?? [];
        $step5 = $enquiry->getStepData(5) ?? [];

        $student = $order?->student;
        $bookedByContact = $student
            ? ! $student->owns_account
            : ! empty($step5['learner_first_name']);

        $learnerFirstName = $student?->first_name
            ?? $step5['learner_first_name']
            ?? $step1['first_name']
            ?? null;

        $greetingName = $bookedByContact
            ? ($student?->contact_first_name ?? $step1['first_name'] ?? null)
            : $learnerFirstName;

        $instructor = $order?->instructor;
        if (! $instructor) {
            $instructorId = $enquiry->getStepData(2)['instructor_id'] ?? null;
            $instructor = $instructorId ? Instructor::with('user')->find($instructorId) : null;
        }

        $firstLesson = $order?->lessons()
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        return [
            'greeting_name' => $greetingName,
            'learner_first_name' => $learnerFirstName,
            'booked_by_contact' => $bookedByContact,
            'package_name' => $order?->package_name,
            'lessons_count' => $order?->package_lessons_count,
            'first_lesson' => $firstLesson ? [
                'date' => $firstLesson->date->format('l j F Y'),
                'start_time' => $firstLesson->start_time?->format('g:ia'),
            ] : null,
            'instructor' => $instructor ? [
                'name' => $instructor->name,
                'first_name' => $instructor->first_name,
                'avatar' => $instructor->avatar,
                'qualification' => $this->qualificationLabel($instructor),
                'rating' => $instructor->rating,
                'reviews' => $instructor->reviews,
            ] : null,
            'guarantee' => [
                'included' => (bool) $order?->includes_test_pass_guarantee,
                'terms_url' => TestPassGuarantee::termsUrl(),
            ],
        ];
    }

    private function qualificationLabel(Instructor $instructor): string
    {
        $status = PdiStatus::tryFrom((string) $instructor->pdi_status);

        return match ($status) {
            PdiStatus::Qualified => 'DVSA Approved Instructor',
            null => 'Driving Instructor',
            default => $status->label(),
        };
    }
}
