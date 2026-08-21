<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TransmissionType;
use App\Models\Instructor;
use Illuminate\Console\Command;

class BackfillInstructorTransmissionType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instructors:backfill-transmission-type {--dry-run : Report what would be backfilled without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off backfill: copy transmission_type from the instructor meta JSON into the transmission_type column (meta is left untouched)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $backfilled = 0;
        $alreadySet = 0;
        $missing = 0;
        $invalid = 0;

        foreach (Instructor::query()->cursor() as $instructor) {
            if ($instructor->transmission_type !== null) {
                $alreadySet++;

                continue;
            }

            $metaValue = $instructor->meta['transmission_type'] ?? null;

            if ($metaValue === null || $metaValue === '') {
                $missing++;
                $this->line("Instructor #{$instructor->id}: no transmission_type in meta — skipped");

                continue;
            }

            $transmissionType = TransmissionType::tryFrom(strtolower(trim((string) $metaValue)));

            if ($transmissionType === null) {
                $invalid++;
                $this->warn("Instructor #{$instructor->id}: invalid meta value '{$metaValue}' — skipped");

                continue;
            }

            if (! $dryRun) {
                $instructor->update(['transmission_type' => $transmissionType->value]);
            }

            $backfilled++;
            $this->line("Instructor #{$instructor->id}: ".($dryRun ? 'would set' : 'set')." transmission_type = {$transmissionType->value}");
        }

        $verb = $dryRun ? 'Would backfill' : 'Backfilled';
        $this->info("{$verb} {$backfilled} instructor(s). Already set: {$alreadySet}. Missing in meta: {$missing}. Invalid: {$invalid}.");

        return self::SUCCESS;
    }
}
