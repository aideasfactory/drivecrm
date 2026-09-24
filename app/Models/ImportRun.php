<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One execution of the legacy importer — who ran it, from where, which file,
 * and what it created. Every row it touched is linked through import_mappings.
 */
class ImportRun extends Model
{
    public const SOURCE_UPLOAD = 'upload';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'source',
        'file_name',
        'status',
        'totals',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'totals' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ImportMapping::class);
    }
}
