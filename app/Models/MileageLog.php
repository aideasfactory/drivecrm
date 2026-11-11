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
        'vehicle_id',
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

    public const FINISH_MILEAGE_MESSAGE = 'Finishing mileage must be the same as or higher than starting mileage.';

    /**
     * Miles driven, or 0 while the finish reading hasn't been logged yet.
     */
    public static function calculateMiles(int $startMileage, ?int $endMileage): int
    {
        return $endMileage === null ? 0 : max($endMileage - $startMileage, 0);
    }

    public function isComplete(): bool
    {
        return $this->end_mileage !== null;
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
