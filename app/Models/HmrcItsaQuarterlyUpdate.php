<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItsaExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HmrcItsaQuarterlyUpdate extends Model
{
    protected $table = 'hmrc_itsa_quarterly_updates';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'submitted_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'digital_records_attested_at' => 'datetime',
            'turnover_pence' => 'integer',
            'other_income_pence' => 'integer',
            'consolidated_expenses_pence' => 'integer',
            'cost_of_goods_pence' => 'integer',
            'payments_to_subcontractors_pence' => 'integer',
            'wages_and_staff_costs_pence' => 'integer',
            'car_van_travel_expenses_pence' => 'integer',
            'premises_running_costs_pence' => 'integer',
            'maintenance_costs_pence' => 'integer',
            'admin_costs_pence' => 'integer',
            'business_entertainment_costs_pence' => 'integer',
            'advertising_costs_pence' => 'integer',
            'interest_on_bank_other_loans_pence' => 'integer',
            'finance_charges_pence' => 'integer',
            'irrecoverable_debts_pence' => 'integer',
            'professional_fees_pence' => 'integer',
            'depreciation_pence' => 'integer',
            'other_expenses_pence' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(HmrcItsaQuarterlyUpdateRevision::class, 'quarterly_update_id')
            ->orderBy('revision_number');
    }

    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digital_records_attested_by_user_id');
    }

    public function isItemised(): bool
    {
        if ($this->consolidated_expenses_pence !== null) {
            return false;
        }

        foreach (ItsaExpenseCategory::cases() as $category) {
            if ($this->{$category->column()} !== null) {
                return true;
            }
        }

        return false;
    }

    public function totalExpensesPence(): int
    {
        if ($this->consolidated_expenses_pence !== null) {
            return (int) $this->consolidated_expenses_pence;
        }

        $total = 0;
        foreach (ItsaExpenseCategory::cases() as $category) {
            $total += (int) ($this->{$category->column()} ?? 0);
        }

        return $total;
    }

    public function nextRevisionNumber(): int
    {
        return (int) ($this->revisions()->max('revision_number') ?? 0) + 1;
    }
}
