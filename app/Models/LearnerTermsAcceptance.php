<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's recorded acceptance of one Learner Terms version in the app.
 */
class LearnerTermsAcceptance extends Model
{
    public const PLATFORMS = ['ios', 'android'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'learner_terms_version_id',
        'accepted_at',
        'platform',
        'app_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LearnerTermsVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LearnerTermsVersion::class, 'learner_terms_version_id');
    }
}
