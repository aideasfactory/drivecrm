<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountDeletionRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'reason',
        'requested_at',
        'scheduled_for',
        'cancelled_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountDeletionRequestStatus::class,
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user this deletion request belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to pending requests only.
     *
     * @param  Builder<AccountDeletionRequest>  $query
     * @return Builder<AccountDeletionRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AccountDeletionRequestStatus::PENDING);
    }

    /**
     * Scope to pending requests whose grace period has elapsed.
     *
     * @param  Builder<AccountDeletionRequest>  $query
     * @return Builder<AccountDeletionRequest>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()->where('scheduled_for', '<=', now());
    }
}
