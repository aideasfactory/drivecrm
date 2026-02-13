<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'note',
    ];

    /**
     * Get the owning noteable model (Instructor or Student).
     */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }
}
