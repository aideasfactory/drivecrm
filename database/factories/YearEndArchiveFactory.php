<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\YearEndArchive;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YearEndArchive>
 */
class YearEndArchiveFactory extends Factory
{
    protected $model = YearEndArchive::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = 2025;

        return [
            'instructor_id' => Instructor::factory(),
            'tax_year_start' => $year,
            'status' => YearEndArchive::STATUS_QUEUED,
            'file_path' => null,
            'file_size_bytes' => null,
            'counts' => null,
            'error_message' => null,
            'queued_at' => now(),
            'generated_at' => null,
            'expires_at' => null,
            'purged_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => YearEndArchive::STATUS_READY,
            'file_size_bytes' => 128,
            'counts' => [
                'finances' => 1,
                'mileage_logs' => 0,
                'receipts' => 0,
                'submissions' => 0,
            ],
            'generated_at' => now(),
            'expires_at' => now()->addYears(6),
        ])->afterCreating(function (YearEndArchive $archive) {
            if ($archive->file_path !== null) {
                return;
            }

            $archive->update([
                'file_path' => sprintf('archives/%d/%d.zip', $archive->instructor_id, $archive->tax_year_start),
            ]);
        });
    }
}
