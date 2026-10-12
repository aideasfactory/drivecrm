<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Instructor;

class BuildPublicInstructorProfileAction
{
    /**
     * Decimal places kept on public coordinates. Two places snaps the pin to
     * a grid of roughly 1km so the instructor's home cannot be pinpointed.
     */
    private const COORDINATE_PRECISION = 2;

    /**
     * Instructor data that is safe to expose to the person making an enquiry.
     * Address, postcode and exact coordinates must never be included.
     *
     * @return array{
     *     id: int,
     *     name: ?string,
     *     first_name: ?string,
     *     last_name: ?string,
     *     avatar: ?string,
     *     bio: ?string,
     *     rating: mixed,
     *     transmission_type: ?string,
     *     priority: bool,
     *     next_available: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     * }
     */
    public function __invoke(Instructor $instructor): array
    {
        return [
            'id' => $instructor->id,
            'name' => $instructor->name,
            'first_name' => $instructor->first_name,
            'last_name' => $instructor->last_name,
            'avatar' => $instructor->avatar,
            'bio' => $instructor->bio,
            'rating' => $instructor->rating,
            'transmission_type' => $instructor->transmission_type,
            'priority' => (bool) $instructor->priority,
            'next_available' => $instructor->next_available ?? null,
            'latitude' => $this->approximate($instructor->latitude),
            'longitude' => $this->approximate($instructor->longitude),
        ];
    }

    private function approximate(mixed $coordinate): ?float
    {
        if (! is_numeric($coordinate)) {
            return null;
        }

        return round((float) $coordinate, self::COORDINATE_PRECISION);
    }
}
