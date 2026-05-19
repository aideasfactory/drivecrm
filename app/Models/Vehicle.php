<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'display_name',
        'registration',
        'engine_size_cc',
        'method',
        'business_use_percentage',
        'acquired_on',
        'disposed_on',
        'lifetime_method_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => VehicleMethod::class,
            'business_use_percentage' => 'float',
            'engine_size_cc' => 'integer',
            'acquired_on' => 'date',
            'disposed_on' => 'date',
            'lifetime_method_locked_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function finances(): HasMany
    {
        return $this->hasMany(InstructorFinance::class);
    }

    public function mileageLogs(): HasMany
    {
        return $this->hasMany(MileageLog::class);
    }

    public function methodLocked(): bool
    {
        return $this->lifetime_method_locked_at !== null;
    }

    public function isDisposed(): bool
    {
        return $this->disposed_on !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('disposed_on');
    }
}
