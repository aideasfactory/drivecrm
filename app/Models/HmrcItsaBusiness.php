<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItsaBusinessType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcItsaBusiness extends Model
{
    protected $table = 'hmrc_itsa_businesses';

    protected $fillable = [
        'user_id',
        'instructor_id',
        'business_id',
        'type_of_business',
        'trading_name',
        'accounting_type',
        'commencement_date',
        'cessation_date',
        'latency_details',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'type_of_business' => ItsaBusinessType::class,
            'commencement_date' => 'date',
            'cessation_date' => 'date',
            'latency_details' => 'array',
            'last_synced_at' => 'datetime',
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
}
