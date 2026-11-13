<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One CSV row from an uploaded import zip, held while the run's jobs work
 * through it. Deleted when the run finishes; never read outside the import.
 */
class ImportStagedRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_run_id',
        'file',
        'instructor_ref',
        'source_ref',
        'line',
        'row',
    ];

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'row' => 'array',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
