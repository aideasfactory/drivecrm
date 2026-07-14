<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hazard_perception_videos', function (Blueprint $table): void {
            $table->string('recap_video_url')->nullable()->after('thumbnail_url')
                ->comment('Explainer video offered after the clip is completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hazard_perception_videos', function (Blueprint $table): void {
            $table->dropColumn('recap_video_url');
        });
    }
};
