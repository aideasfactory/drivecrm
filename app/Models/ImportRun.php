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

    /** An import:* console command run against a single CSV (e.g. import:lessons). */
    public const SOURCE_COMMAND = 'command';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'source',
        'file_name',
        'status',
        'dry_run',
        'stage',
        'totals',
        'stage_totals',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'totals' => 'array',
            'stage_totals' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function stagedRows(): HasMany
    {
        return $this->hasMany(ImportStagedRow::class);
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
