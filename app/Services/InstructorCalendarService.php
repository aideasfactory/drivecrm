<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\BuildInstructorCalendarFeedAction;
use App\Actions\Instructor\EnsureCalendarFeedTokenAction;
use App\Actions\Instructor\GetInstructorCalendarItemsAction;
use App\Actions\Instructor\GetInstructorCalendarItemsInRangeAction;
use App\Actions\Instructor\RegenerateCalendarFeedTokenAction;
use App\Actions\Onboarding\PreviewPackageClashesAction;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Collection;

class InstructorCalendarService extends BaseService
{
    public function __construct(
        protected GetInstructorCalendarItemsAction $getCalendarItems,
        protected GetInstructorCalendarItemsInRangeAction $getCalendarItemsInRange,
        protected PreviewPackageClashesAction $previewPackageClashes,
        protected EnsureCalendarFeedTokenAction $ensureCalendarFeedToken,
        protected RegenerateCalendarFeedTokenAction $regenerateCalendarFeedToken,
        protected BuildInstructorCalendarFeedAction $buildCalendarFeed,
    ) {}

    /**
     * Every weekly lesson date a package starting in this slot would get, and
     * what each one clashes with, and whether it sits in a pre-made slot. Read-only.
     *
     * @return list<array{lesson_number: int, date: string, start_time: string, end_time: string, clashes: list<string>, in_slot: bool}>
     */
    public function previewPackageClashes(CalendarItem $firstSlot, int $lessonsCount): array
    {
        return ($this->previewPackageClashes)($firstSlot, $lessonsCount);
    }

    /**
     * Get calendar items for an instructor on a specific date.
     *
     * @param  bool  $availableOnly  When true, returns only available slots. When false, returns all items for the day.
     * @param  bool  $excludeDrafts  When true, excludes items with draft status.
     * @return Collection<int, CalendarItem>
     */
    public function getCalendarItems(Instructor $instructor, string $date, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        $suffixParts = ["calendar:{$date}"];
        if (! $availableOnly) {
            $suffixParts[] = 'all';
        }
        if (! $excludeDrafts) {
            $suffixParts[] = 'drafts';
        }
        $suffix = implode(':', $suffixParts);
        $key = $this->cacheKey('instructor', $instructor->id, $suffix);

        return $this->remember($key, fn () => ($this->getCalendarItems)($instructor, $date, $availableOnly, $excludeDrafts));
    }

    /**
     * Get calendar items for an instructor between two dates (inclusive).
     *
     * Not cached: invalidation is keyed per date, so a cached range could go
     * stale when any day inside it changes.
     *
     * @return Collection<int, CalendarItem>
     */
    public function getCalendarItemsInRange(Instructor $instructor, string $from, string $to, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        return ($this->getCalendarItemsInRange)($instructor, $from, $to, $availableOnly, $excludeDrafts);
    }

    /**
     * Invalidate cached calendar items for a specific instructor and date.
     *
     * Should be called whenever calendar items are booked, reserved, or otherwise modified.
     */
    public function invalidateCalendarCache(int $instructorId, string $date): void
    {
        $this->invalidate([
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}"),
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}:all"),
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}:drafts"),
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}:all:drafts"),
        ]);
    }

    /**
     * Links to the instructor's subscribable calendar feed, creating its
     * secret token on first use.
     *
     * @return array{url: string, webcal_url: string, google_url: string}
     */
    public function calendarFeedLinks(Instructor $instructor): array
    {
        return $this->feedLinksFor(($this->ensureCalendarFeedToken)($instructor));
    }

    /**
     * Links to the instructor's calendar feed if one has been created, without
     * creating it.
     *
     * @return array{url: string, webcal_url: string, google_url: string}|null
     */
    public function existingCalendarFeedLinks(Instructor $instructor): ?array
    {
        return filled($instructor->calendar_feed_token)
            ? $this->feedLinksFor($instructor->calendar_feed_token)
            : null;
    }

    /**
     * Replace the feed token, revoking the old URL, and return the new links.
     * Creates the first token when there is none yet.
     *
     * @return array{url: string, webcal_url: string, google_url: string}
     */
    public function resetCalendarFeed(Instructor $instructor): array
    {
        return $this->feedLinksFor(($this->regenerateCalendarFeedToken)($instructor));
    }

    /**
     * The iCalendar document for a feed token, or null when no instructor has it.
     * Not cached: it would go stale between per-date cache invalidations.
     */
    public function calendarFeedForToken(string $token): ?string
    {
        $instructor = Instructor::query()->where('calendar_feed_token', $token)->first();

        return $instructor ? ($this->buildCalendarFeed)($instructor) : null;
    }

    /**
     * @return array{url: string, webcal_url: string, google_url: string}
     */
    private function feedLinksFor(string $token): array
    {
        $url = route('calendar.feed', ['token' => $token]);
        $webcalUrl = (string) preg_replace('#^https?://#', 'webcal://', $url);

        return [
            'url' => $url,
            'webcal_url' => $webcalUrl,
            'google_url' => 'https://calendar.google.com/calendar/r?cid='.rawurlencode($webcalUrl),
        ];
    }
}
