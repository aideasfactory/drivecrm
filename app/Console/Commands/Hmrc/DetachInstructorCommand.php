<?php

declare(strict_types=1);

namespace App\Console\Commands\Hmrc;

use App\Models\HmrcDeviceIdentifier;
use App\Models\HmrcItsaBusiness;
use App\Models\HmrcItsaCalculation;
use App\Models\HmrcItsaFinalDeclaration;
use App\Models\HmrcItsaObligation;
use App\Models\HmrcItsaQuarterlyUpdate;
use App\Models\HmrcItsaQuarterlyUpdateRevision;
use App\Models\HmrcItsaSupplementaryData;
use App\Models\HmrcOAuthState;
use App\Models\HmrcToken;
use App\Models\HmrcTokenRefreshLog;
use App\Models\HmrcVatObligation;
use App\Models\HmrcVatReturn;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetachInstructorCommand extends Command
{
    protected $signature = 'hmrc:detach-instructor
        {user : User ID or email address}
        {--dry-run : Show what would be deleted without writing anything}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Fully detach an instructor from HMRC/MTD: deletes OAuth tokens, businesses, obligations, all submitted returns, and wipes the tax profile (NINO, UTR, VRN, business_type) so they can reconnect and re-sync from a clean slate.';

    /**
     * Models keyed by the column scoped to the user. Order matters — child rows
     * before parents to keep FK constraints happy even though most cascade.
     *
     * @var array<class-string, string>
     */
    private const SCOPED_MODELS = [
        HmrcItsaSupplementaryData::class => 'user_id',
        HmrcItsaFinalDeclaration::class => 'user_id',
        HmrcItsaCalculation::class => 'user_id',
        HmrcItsaQuarterlyUpdateRevision::class => 'user_id',
        HmrcItsaQuarterlyUpdate::class => 'user_id',
        HmrcItsaObligation::class => 'user_id',
        HmrcItsaBusiness::class => 'user_id',
        HmrcVatReturn::class => 'user_id',
        HmrcVatObligation::class => 'user_id',
        HmrcTokenRefreshLog::class => 'user_id',
        HmrcDeviceIdentifier::class => 'user_id',
        HmrcOAuthState::class => 'user_id',
        HmrcToken::class => 'user_id',
    ];

    /**
     * Tax-profile fields nulled on the instructor row. Kept separate from
     * `mtd_itsa_status*` so the reset logic stays one place.
     *
     * @var array<int, string>
     */
    private const TAX_PROFILE_FIELDS = [
        'business_type',
        'vat_registered',
        'vrn',
        'utr',
        'nino',
        'companies_house_number',
        'tax_profile_completed_at',
    ];

    public function handle(): int
    {
        $user = $this->resolveUser((string) $this->argument('user'));
        if ($user === null) {
            $this->error("User '{$this->argument('user')}' not found.");

            return self::FAILURE;
        }

        $counts = $this->countAll($user);
        $total = array_sum($counts);

        $this->info("Target: {$user->email} (id={$user->id})");
        $this->table(['Table', 'Rows'], array_map(
            fn (string $table, int $count): array => [$table, $count],
            array_keys($counts),
            array_values($counts),
        ));

        $instructor = $user->instructor;
        $hasItsaStatus = $instructor !== null && $instructor->mtd_itsa_status !== null && $instructor->mtd_itsa_status->value !== 'unknown';
        $hasTaxProfile = $instructor !== null && $this->hasAnyTaxProfileData($instructor);

        if ($hasItsaStatus) {
            $this->line("Instructor MTD ITSA status currently '{$instructor->mtd_itsa_status->value}' — will reset to 'unknown'.");
        }
        if ($hasTaxProfile) {
            $this->line('Instructor tax profile (business_type / VRN / UTR / NINO / CH number / completed_at) — will be cleared.');
        }

        if ($total === 0 && ! $hasItsaStatus && ! $hasTaxProfile) {
            $this->info('Nothing to detach — user already clean.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$total} HMRC row(s) for {$user->email}?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(function () use ($user, $instructor): array {
            $tally = [];
            foreach (self::SCOPED_MODELS as $model => $column) {
                $tally[$this->tableFor($model)] = $model::query()->where($column, $user->id)->delete();
            }

            if ($instructor !== null) {
                $instructor->forceFill(array_merge(
                    array_fill_keys(self::TAX_PROFILE_FIELDS, null),
                    [
                        'vat_registered' => false,
                        'mtd_itsa_status' => 'unknown',
                        'mtd_itsa_status_checked_at' => null,
                    ],
                ))->save();
            }

            return $tally;
        });

        $totalDeleted = array_sum($deleted);
        $this->info("Deleted {$totalDeleted} row(s) across ".count($deleted).' tables.');

        Log::info('hmrc.detach_instructor', [
            'user_id' => $user->id,
            'email' => $user->email,
            'deleted' => $deleted,
            'reset_instructor' => $instructor !== null,
            'actor' => 'artisan',
        ]);

        return self::SUCCESS;
    }

    private function resolveUser(string $identifier): ?User
    {
        if (ctype_digit($identifier)) {
            return User::find((int) $identifier);
        }

        return User::query()->where('email', $identifier)->first();
    }

    /**
     * @return array<string, int>
     */
    private function countAll(User $user): array
    {
        $counts = [];
        foreach (self::SCOPED_MODELS as $model => $column) {
            $counts[$this->tableFor($model)] = $model::query()->where($column, $user->id)->count();
        }

        return $counts;
    }

    /**
     * @param  class-string  $model
     */
    private function tableFor(string $model): string
    {
        return (new $model)->getTable();
    }

    private function hasAnyTaxProfileData(Instructor $instructor): bool
    {
        foreach (self::TAX_PROFILE_FIELDS as $field) {
            $value = $instructor->getAttribute($field);
            if ($field === 'vat_registered') {
                if ($value === true) {
                    return true;
                }
                continue;
            }
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
