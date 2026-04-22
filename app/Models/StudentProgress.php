<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgress extends Model
{
    use HasFactory;

    protected $table = 'student_progress';

    protected $fillable = [
        'student_id',
        'progress_subcategory_id',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ProgressSubcategory::class, 'progress_subcategory_id');
    }
}
