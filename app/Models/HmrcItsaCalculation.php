<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItsaCalculationStatus;
use App\Enums\ItsaCalculationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HmrcItsaCalculation extends Model
{
    protected $table = 'hmrc_itsa_calculations';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'calculation_type' => ItsaCalculationType::class,
            'status' => ItsaCalculationStatus::class,
            'triggered_at' => 'datetime',
            'processed_at' => 'datetime',
            'summary_payload' => 'array',
            'detail_payload' => 'array',
            'error_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function finalDeclaration(): HasOne
    {
        return $this->hasOne(HmrcItsaFinalDeclaration::class, 'calculation_id');
    }

    public function isProcessed(): bool
    {
        return $this->status === ItsaCalculationStatus::Processed;
    }
}
