<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Str;

class EnsureCalendarFeedTokenAction
{
    /**
     * The instructor's calendar feed token, created on first use. Only fills
     * an empty column, so two first requests at once can't hand out a URL
     * that is immediately replaced.
     */
    public function __invoke(Instructor $instructor): string
    {
        if (filled($instructor->calendar_feed_token)) {
            return $instructor->calendar_feed_token;
        }

        Instructor::query()
            ->whereKey($instructor->id)
            ->whereNull('calendar_feed_token')
            ->update(['calendar_feed_token' => Str::random(40)]);

        $instructor->calendar_feed_token = Instructor::query()
            ->whereKey($instructor->id)
            ->value('calendar_feed_token');
        $instructor->syncOriginalAttribute('calendar_feed_token');

        return $instructor->calendar_feed_token;
    }
}
