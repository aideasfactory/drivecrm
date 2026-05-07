<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcItsaQuarterlyUpdateRevision extends Model
{
    protected $table = 'hmrc_itsa_quarterly_update_revisions';

    protected $fillable = [
        'quarterly_update_id',
        'user_id',
        'revision_number',
        'kind',
        'request_payload',
        'response_payload',
        'submission_id',
        'correlation_id',
        'submitted_at',
        'submitted_by_user_id',
        'digital_records_attested_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'digital_records_attested_at' => 'datetime',
        ];
    }

    public function quarterlyUpdate(): BelongsTo
    {
        return $this->belongsTo(HmrcItsaQuarterlyUpdate::class, 'quarterly_update_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
