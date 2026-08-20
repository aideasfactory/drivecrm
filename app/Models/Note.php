<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'user_id',
        'note',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Get the owning noteable model (Instructor or Student).
     */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who wrote the note (null for notes created before authorship tracking).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
