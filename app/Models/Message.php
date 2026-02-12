<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'from',
        'to',
        'message',
    ];

    protected function casts(): array
    {
        return [];
    }

    /**
     * Get the user who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from');
    }

    /**
     * Get the user who received the message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to');
    }
}
