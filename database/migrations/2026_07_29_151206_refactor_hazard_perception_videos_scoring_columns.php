<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hazard_perception_videos', function (Blueprint $table): void {
            $table->boolean('has_recap')->default(false)->after('thumbnail_url')
                ->comment('Whether a recap (explainer) step exists for this clip — the app skips it when false');
        });

        DB::table('hazard_perception_videos')
            ->whereNotNull('recap_video_url')
            ->update(['has_recap' => true]);

        $this->backfillScoringZones();

        Schema::table('hazard_perception_videos', function (Blueprint $table): void {
            $table->dropColumn(['hazard_1_start', 'hazard_1_end', 'hazard_2_start', 'hazard_2_end']);
        });
    }

    public function down(): void
    {
        Schema::table('hazard_perception_videos', function (Blueprint $table): void {
            $table->decimal('hazard_1_start', 6, 2)->nullable()->after('duration_seconds');
            $table->decimal('hazard_1_end', 6, 2)->nullable()->after('hazard_1_start');
            $table->decimal('hazard_2_start', 6, 2)->nullable()->after('hazard_1_end');
            $table->decimal('hazard_2_end', 6, 2)->nullable()->after('hazard_2_start');
        });

        foreach (DB::table('hazard_perception_scoring_zones')
            ->selectRaw('hazard_perception_video_id, hazard_number, MIN(start_seconds) as window_start, MAX(end_seconds) as window_end')
            ->groupBy('hazard_perception_video_id', 'hazard_number')
            ->get() as $window) {
            $prefix = (int) $window->hazard_number === 2 ? 'hazard_2' : 'hazard_1';

            DB::table('hazard_perception_videos')
                ->where('id', $window->hazard_perception_video_id)
                ->update([
                    "{$prefix}_start" => $window->window_start,
                    "{$prefix}_end" => $window->window_end,
                ]);
        }

        DB::table('hazard_perception_scoring_zones')->delete();

        Schema::table('hazard_perception_videos', function (Blueprint $table): void {
            $table->dropColumn('has_recap');
        });
    }

    /**
     * Recreate the legacy behaviour as explicit zones: split each hazard
     * window into 5 equal bands, earliest band scoring 5 points.
     */
    private function backfillScoringZones(): void
    {
        $now = now();

        foreach (DB::table('hazard_perception_videos')
            ->select('id', 'hazard_1_start', 'hazard_1_end', 'hazard_2_start', 'hazard_2_end')
            ->get() as $video) {
            $windows = [
                1 => [$video->hazard_1_start, $video->hazard_1_end],
                2 => [$video->hazard_2_start, $video->hazard_2_end],
            ];

            foreach ($windows as $hazardNumber => [$start, $end]) {
                if ($start === null || $end === null) {
                    continue;
                }

                $bandLength = ((float) $end - (float) $start) / 5;
                $rows = [];

                foreach ([5, 4, 3, 2, 1] as $index => $score) {
                    $rows[] = [
                        'hazard_perception_video_id' => $video->id,
                        'hazard_number' => $hazardNumber,
                        'score' => $score,
                        'start_seconds' => round((float) $start + ($bandLength * $index), 2),
                        'end_seconds' => round((float) $start + ($bandLength * ($index + 1)), 2),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('hazard_perception_scoring_zones')->insert($rows);
            }
        }
    }
};
