<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcItsaFinalDeclaration extends Model
{
    protected $table = 'hmrc_itsa_final_declarations';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'digital_records_attested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(HmrcItsaCalculation::class, 'calculation_id');
    }

    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digital_records_attested_by_user_id');
    }
}
