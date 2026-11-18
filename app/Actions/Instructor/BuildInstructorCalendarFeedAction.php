<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;
use App\Support\AlternativePayments;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildInstructorCalendarFeedAction
{
    private const TIMEZONE = 'Europe/London';

    private const PAST_DAYS = 30;

    private const FUTURE_MONTHS = 12;

    /**
     * The instructor's booked lessons and practical tests as an iCalendar
     * (RFC 5545) document, for calendar apps to subscribe to.
     *
     * Includes reserved, booked and completed lessons, holds the instructor or
     * Head Office made, and every practical test. A pupil's own unpaid checkout
     * hold is left out, as in the diary. Each event carries the pupil's name
     * and, when they have one, their default pickup point. Nothing else about
     * the pupil is shared.
     */
    public function __invoke(Instructor $instructor): string
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        $calendars = $instructor->calendars()
            ->whereBetween('date', [
                $today->subDays(self::PAST_DAYS)->toDateString(),
                $today->addMonths(self::FUTURE_MONTHS)->toDateString(),
            ])
            ->with([
                'items' => fn (HasMany $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('item_type', CalendarItemType::PracticalTest)
                        ->orWhere(fn (Builder $query) => $query
                            ->whereIn('status', [
                                CalendarItemStatus::DRAFT,
                                CalendarItemStatus::RESERVED,
                                CalendarItemStatus::BOOKED,
                                CalendarItemStatus::COMPLETED,
                            ])
                            ->where(fn (Builder $query) => $query
                                ->whereNull('item_type')
                                ->orWhere('item_type', CalendarItemType::Slot))))
                    ->orderBy('start_time'),
                'items.lessons' => fn (HasMany $query) => $query->where('status', '!=', LessonStatus::CANCELLED),
                'items.lessons.order:id,student_id,source',
                'items.lessons.order.student:id,first_name,surname',
                'items.lessons.order.student.pickupPoints' => fn (HasMany $query) => $query->where('is_default', true),
                'items.student:id,first_name,surname',
                'items.student.pickupPoints' => fn (HasMany $query) => $query->where('is_default', true),
            ])
            ->orderBy('date')
            ->get();

        $events = [];

        foreach ($calendars as $calendar) {
            foreach ($calendar->items as $item) {
                $event = $this->event($calendar, $item);

                if ($event !== null) {
                    $events[] = $event;
                }
            }
        }

        return $this->document($events);
    }

    /**
     * @return list<string>|null
     */
    private function event(Calendar $calendar, CalendarItem $item): ?array
    {
        /** @var Lesson|null $lesson */
        $lesson = $item->lessons->first();
        $isTest = $item->isPracticalTest() || $lesson?->is_practical_test === true;

        if (! $isTest && $lesson === null) {
            return null;
        }

        if ($item->status === CalendarItemStatus::DRAFT
            && ($lesson?->order === null || ! AlternativePayments::isStaffOrInstructorBooking($lesson->order))) {
            return null;
        }

        $date = $calendar->date->format('Y-m-d');
        $start = CarbonImmutable::parse("{$date} {$item->start_time}", self::TIMEZONE);
        $end = CarbonImmutable::parse("{$date} {$item->end_time}", self::TIMEZONE);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        /** @var Student|null $student */
        $student = $lesson?->order?->student ?? $item->student;
        $pupilName = $student ? trim($student->first_name.' '.$student->surname) : '';
        $type = $isTest ? 'Driving test' : 'Lesson';
        $pickupPoint = $student?->pickupPoints->first();
        $location = $pickupPoint
            ? trim(implode(', ', array_filter([$pickupPoint->address, $pickupPoint->postcode])))
            : '';

        $uid = $lesson ? "lesson-{$lesson->id}" : "calendar-item-{$item->id}";
        $lastModified = collect([$item->updated_at, $lesson?->updated_at])->filter()->max();

        $lines = [
            'BEGIN:VEVENT',
            "UID:{$uid}@".$this->uidHost(),
            'DTSTAMP:'.CarbonImmutable::now('UTC')->format('Ymd\THis\Z'),
            'DTSTART;TZID='.self::TIMEZONE.':'.$start->format('Ymd\THis'),
            'DTEND;TZID='.self::TIMEZONE.':'.$end->format('Ymd\THis'),
            'SUMMARY:'.$this->escape($pupilName !== '' ? "{$type} - {$pupilName}" : $type),
            'STATUS:'.($item->status === CalendarItemStatus::DRAFT ? 'TENTATIVE' : 'CONFIRMED'),
            'TRANSP:OPAQUE',
        ];

        if ($location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($lastModified !== null) {
            $lines[] = 'LAST-MODIFIED:'.CarbonImmutable::instance($lastModified)->utc()->format('Ymd\THis\Z');
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * @param  list<list<string>>  $events
     */
    private function document(array $events): string
    {
        $calendarName = config('app.name').' lessons';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escape((string) config('app.name')).'//Instructor Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($calendarName),
            'X-WR-TIMEZONE:'.self::TIMEZONE,
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
            ...$this->londonTimezone(),
            ...array_merge(...$events ?: [[]]),
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * Europe/London rules (BST from the last Sunday in March, GMT from the
     * last Sunday in October), so clients without the IANA zone still place
     * events correctly.
     *
     * @return list<string>
     */
    private function londonTimezone(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:'.self::TIMEZONE,
            'X-LIC-LOCATION:'.self::TIMEZONE,
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0000',
            'TZOFFSETTO:+0100',
            'TZNAME:BST',
            'DTSTART:19700329T010000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0000',
            'TZNAME:GMT',
            'DTSTART:19701025T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    private function uidHost(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'drive-crm';
    }

    private function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $text,
        );
    }

    /**
     * Fold a content line to at most 75 octets per line (RFC 5545 3.1)
     * without splitting a multi-byte character.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = [];
        $current = '';

        foreach (mb_str_split($line) as $character) {
            $limit = $folded === [] ? 75 : 74;

            if (strlen($current) + strlen($character) > $limit) {
                $folded[] = $current;
                $current = '';
            }

            $current .= $character;
        }

        $folded[] = $current;

        return implode("\r\n ", $folded);
    }
}
