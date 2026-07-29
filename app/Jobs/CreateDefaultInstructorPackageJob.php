<?php

namespace App\Jobs;

use App\Actions\Instructor\CreateInstructorPackageAction;
use App\Models\Instructor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateDefaultInstructorPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DEFAULT_NAME = 'Starter Package';

    private const DEFAULT_DESCRIPTION = 'Two driving lessons — a 4-hour slot.';

    private const DEFAULT_TOTAL_PRICE_PENCE = 6000;

    private const DEFAULT_LESSONS_COUNT = 2;

    public function __construct(
        public Instructor $instructor
    ) {}

    public function handle(CreateInstructorPackageAction $createInstructorPackage): void
    {
        $this->instructor->refresh();

        if (! $this->instructor->hasCompletedOnboarding() || ! $this->instructor->charges_enabled) {
            Log::info('Skipping default package creation — Stripe onboarding not complete', [
                'instructor_id' => $this->instructor->id,
            ]);

            return;
        }

        if ($this->instructor->packages()->exists()) {
            Log::info('Skipping default package creation — instructor already has packages', [
                'instructor_id' => $this->instructor->id,
            ]);

            return;
        }

        $package = ($createInstructorPackage)($this->instructor, [
            'name' => self::DEFAULT_NAME,
            'description' => self::DEFAULT_DESCRIPTION,
            'total_price_pence' => self::DEFAULT_TOTAL_PRICE_PENCE,
            'lessons_count' => self::DEFAULT_LESSONS_COUNT,
        ]);

        Log::info('Default package created for instructor after Stripe Connect', [
            'instructor_id' => $this->instructor->id,
            'package_id' => $package->id,
        ]);
    }
}
