<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPickupPoint extends Model
{
    /** @use HasFactory<\Database\Factories\StudentPickupPointFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'label',
        'address',
        'postcode',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the student that owns this pickup point.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Scope to get only the default pickup point.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
