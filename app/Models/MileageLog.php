<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MileageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'date',
        'start_mileage',
        'end_mileage',
        'miles',
        'type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_mileage' => 'integer',
            'end_mileage' => 'integer',
            'miles' => 'integer',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function scopeBusiness($query)
    {
        return $query->where('type', 'business');
    }

    public function scopePersonal($query)
    {
        return $query->where('type', 'personal');
    }
}
