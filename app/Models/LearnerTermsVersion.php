<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of the DRIVE+ Learner Terms shown in the student app.
 * `published_at` NULL = the editable draft. Published rows are immutable.
 */
class LearnerTermsVersion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'version',
        'body',
        'published_at',
        'created_by_user_id',
        'published_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LearnerTermsAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(LearnerTermsAcceptance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * @param  Builder<LearnerTermsVersion>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * @param  Builder<LearnerTermsVersion>  $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->whereNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
