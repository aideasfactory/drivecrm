<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single message within a mirrored Bird conversation, keyed by Bird's
 * message UUID and upserted by the bird:sync command.
 */
class BirdMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'bird_conversation_id',
        'sender_type',
        'sender_name',
        'text',
        'sent_at',
    ];

    /**
     * @return BelongsTo<BirdConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BirdConversation::class, 'bird_conversation_id');
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
