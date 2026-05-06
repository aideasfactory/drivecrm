<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItsaObligationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcVatObligation extends Model
{
    protected $table = 'hmrc_vat_obligations';

    protected $fillable = [
        'user_id',
        'vrn',
        'period_key',
        'period_start_date',
        'period_end_date',
        'due_date',
        'received_date',
        'status',
        'last_reminder_sent_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'due_date' => 'date',
            'received_date' => 'date',
            'status' => ItsaObligationStatus::class,
            'last_reminder_sent_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ItsaObligationStatus::Open;
    }

    public function daysUntilDue(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
    }
}
