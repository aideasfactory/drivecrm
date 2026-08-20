<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-only local mirror of a Bird AI Employee conversation.
 *
 * The primary key is Bird's conversation UUID; rows are upserted by the
 * bird:sync command and never written from anywhere else.
 */
class BirdConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'last_message',
        'status',
        'channel_id',
        'last_activity_at',
    ];

    /**
     * @return HasMany<BirdMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BirdMessage::class);
    }

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }
}
