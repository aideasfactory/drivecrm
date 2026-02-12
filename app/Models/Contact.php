<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    protected $fillable = [
        'contactable_type',
        'contactable_id',
        'name',
        'relationship',
        'phone',
        'email',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Get the owning contactable model (Instructor or Student).
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get only primary contacts.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
