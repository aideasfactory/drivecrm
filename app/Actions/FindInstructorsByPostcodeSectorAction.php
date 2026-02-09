<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class FindInstructorsByPostcodeSectorAction
{
    /**
     * Find instructors by postcode sector match.
     *
     * @param  string  $postcode  Full postcode (e.g., "TS7 1AB")
     * @return Collection Formatted instructor data
     */
    public function __invoke(string $postcode): Collection
    {
        // Extract postcode sector (everything before the space)
        $postcodeSector = $this->extractPostcodeSector($postcode);

        if (! $postcodeSector) {
            return collect();
        }

        // Find instructors who cover this postcode sector
        $instructors = Instructor::query()
            ->active()
            ->whereHas('locations', function ($query) use ($postcodeSector) {
                $query->where('postcode_sector', $postcodeSector);
            })
            ->with([
                'user',
                'locations',
                'calendars' => function ($query) {
                    // Get calendars starting from 2 days from now
                    $query->where('date', '>=', now()->addDays(2)->startOfDay())
                        ->where('date', '<=', now()->addDays(30))
                        ->orderBy('date', 'asc')
                        ->with(['availableItems']);
                },
            ])
            ->orderByDesc('priority')
            ->get();

        return $instructors;
    }

    /**
     * Extract postcode sector from full postcode.
     *
     * @param  string  $postcode  Full postcode (e.g., "TS7 1AB", "NE12 8GH")
     * @return string|null Postcode sector (e.g., "TS7", "NE12")
     */
    private function extractPostcodeSector(string $postcode): ?string
    {
        $normalized = strtoupper(trim($postcode));

        if (empty($normalized)) {
            return null;
        }

        // Extract everything before the space
        $parts = explode(' ', $normalized);

        return $parts[0] ?? null;
    }

    /**
     * Format instructors for response.
     */
    private function formatInstructors(Collection $instructors): Collection
    {
        return $instructors->map(function ($instructor) {
            $meta = is_string($instructor->meta) ? json_decode($instructor->meta, true) : ($instructor->meta ?? []);

            // Get next available slot from calendar
            $nextAvailable = $this->getNextAvailableSlot($instructor);

            return [
                'id' => $instructor->id,
                'name' => $instructor->user->name,
                'image' => $meta['avatar'] ?? 'https://ui-avatars.com/api/?name='.urlencode($instructor->user->name).'&background=0D8ABC&color=fff',
                'transmissions' => $meta['transmissions'] ?? [$instructor->transmission_type],
                'experience' => $meta['experience'] ?? null,
                'passRate' => $meta['pass_rate'] ?? null,
                'totalStudents' => $meta['total_students'] ?? null,
                'rating' => $instructor->rating,
                'reviews' => $meta['reviews'] ?? 0,
                'specialties' => $meta['specialties'] ?? [],
                'qualifications' => $meta['qualifications'] ?? [],
                'languages' => $meta['languages'] ?? ['English'],
                'bio' => $instructor->bio,
                'isTopPick' => $instructor->priority,
                'specialOffer' => $meta['special_offer'] ?? null,
                'address' => $instructor->address,
                'postcode' => $instructor->postcode,
                'location' => $instructor->address,
                'distance' => null, // No distance calculation for simple sector match
                'nextAvailable' => $nextAvailable,
                'priority' => $instructor->priority,
                'latitude' => $instructor->latitude,
                'longitude' => $instructor->longitude,
            ];
        });
    }

    /**
     * Get the next available slot from instructor's calendar.
     */
    private function getNextAvailableSlot($instructor): ?string
    {
        if (! $instructor->relationLoaded('calendars') || $instructor->calendars->isEmpty()) {
            return null;
        }

        foreach ($instructor->calendars as $calendar) {
            if ($calendar->availableItems->isNotEmpty()) {
                $date = $calendar->date;
                $firstSlot = $calendar->availableItems->first();

                $slotDateTime = $date->copy()->setTimeFrom($firstSlot->start_time);

                if ($date->diffInDays(now()) <= 6) {
                    $dayLabel = $date->format('l');
                } else {
                    $dayLabel = $date->format('M j');
                }

                $timeLabel = $slotDateTime->format('ga');

                return $dayLabel.' '.$timeLabel;
            }
        }

        return null;
    }
}
