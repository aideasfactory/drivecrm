<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Str;

class RegenerateCalendarFeedTokenAction
{
    /**
     * Replace the instructor's calendar feed token. The old feed URL stops
     * working straight away, so any calendar subscribed to it stops updating.
     */
    public function __invoke(Instructor $instructor): string
    {
        $instructor->forceFill(['calendar_feed_token' => Str::random(40)])->save();

        $instructor->logActivity('Calendar feed link reset', 'instructor');

        return $instructor->calendar_feed_token;
    }
}
