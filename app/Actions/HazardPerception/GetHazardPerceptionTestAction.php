<?php

declare(strict_types=1);

namespace App\Actions\HazardPerception;

use App\Models\HazardPerceptionTest;

class GetHazardPerceptionTestAction
{
    /**
     * Load a test with everything the results/resume view needs: videos in
     * playback order and the attempts recorded so far.
     */
    public function __invoke(HazardPerceptionTest $test): HazardPerceptionTest
    {
        return $test->load(['videos', 'attempts']);
    }
}
